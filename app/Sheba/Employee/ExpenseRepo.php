<?php namespace Sheba\Employee;

use App\Models\Attachment;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Sheba\Attachments\FilesAttachment;
use Sheba\Dal\Expense\Expense;
use Sheba\ModificationFields;
use Throwable;

class ExpenseRepo
{
    use ModificationFields;
    use FilesAttachment;

    public function index(Request $request, $member)
    {
        try {
            $business_member = $member->activeBusinessMember->first();
            $expenses = Expense::where('business_member_id', $business_member->id)
                ->select('id', 'member_id', 'amount', 'status', 'is_updated_by_super_admin', 'remarks', 'type', 'created_at')
                ->orderBy('id', 'desc');

            if ($request->has('status')) $expenses = $expenses->where('status', $request->status);

            $start_date = $request->has('start_date') ? $request->start_date : null;
            $end_date = $request->has('end_date') ? $request->end_date : null;
            if ($start_date && $end_date) {
                $expenses->whereBetween('created_at', [$start_date . ' 00:00:00', $end_date . ' 23:59:59']);
            }

            $expenses = $expenses->get();

            return $expenses;
        } catch (Throwable $e) {
            return null;
        }
    }

    public function filterMonth($year, $month, $request)
    {
        try {
            if(!$year) $year = Carbon::now()->year;
            $date = Carbon::createFromFormat('Y-m', $year.'-'.$month);
            $start_date = $date->startOfMonth()->toDateTimeString();
            $end_date = $date->endOfMonth()->toDateTimeString();
            $expenses = Expense::where('business_member_id', $request->business_member_id)
                ->whereBetween('created_at', [$start_date, $end_date])
                ->select('id', 'member_id', 'business_member_id', 'amount', 'status', 'remarks', 'type', 'created_at')
                ->orderBy('id', 'desc')
                ->get();
            foreach ($expenses as $expense) {
                $expense['employee_name'] = $expense->member->profile->name;
                $expense['employee_department'] = $expense->member->businessMember->department() ? $expense->member->businessMember->department()->name : null;
                $expense['employee_designation'] = $expense->member->businessMember->role ? $expense->member->businessMember->role->name : null;
                $expense['attachment'] = $this->getAttachments($expense, $request) ? $this->getAttachments($expense, $request) : null;
                unset($expense->businessMember);
                unset($expense->member);
            }
            return $expenses;
        } catch (Throwable $e) {
            return false;
        }

    }

    public function store(Request $request, $member)
    {

        try {
            $business_member = $member->activeBusinessMember->first();
            $expense = new Expense();
            $expense->amount = $request->amount;
            $expense->member_id = $member->id;
            $expense->business_member_id = $business_member->id;
            $expense->remarks = $request->remarks;
            $expense->type = $request->type;

            $expense->save();

            if ($request['file']) {
                $this->storeAttachment($expense, $request, $member);
            }

            return ['expense' => ['id' => $expense->id]];
        } catch (Throwable $e) {
            return false;
        }
    }

    public function show(Request $request, $expense)
    {
        try {
            $expense = Expense::where('id', $expense)
                ->orderBy('created_at', 'DESC')
                ->select('id', 'member_id', 'business_member_id', 'amount', 'status', 'is_updated_by_super_admin', 'remarks', 'type', 'created_at', 'updated_at')->first();

            if (!$expense) return false;

            $expense['date'] = $expense->created_at ? $expense->created_at->format('M d') : null;
            $expense['time'] = $expense->created_at ? $expense->created_at->format('h:i A') : null;
            $expense['can_edit'] = $expense->is_updated_by_super_admin ? 0 : $this->canEdit($expense);
            $expense['reason'] = $expense->is_updated_by_super_admin ? "Expense already updated by admin at " . $expense->updated_at->format('d-M-Y h:i A') : null;

            if ($this->getAttachments($expense, $request)) $expense['attachment'] = $this->getAttachments($expense, $request);

            return ['expense' => $expense];
        } catch (Throwable $e) {
            return false;
        }
    }

    private function canEdit(Expense $expense)
    {
        $created_at = $expense->created_at;
        if ($created_at->month == 12) $can_edit_until = Carbon::create($created_at->year + 1, 1, 5, 23, 59, 59);
        else $can_edit_until = Carbon::create($created_at->year, $created_at->month + 1, 5, 23, 59, 59);
        return Carbon::now()->lte($can_edit_until) ? 1 : 0;
    }

    public function update(Request $request, $expense, $member, $from_web_portal = null)
    {
        try {
            $expense = Expense::find($expense);
            if (!$expense) return false;

            $expense->amount = $request->amount;
            if ($request->remarks) $expense->remarks = $request->remarks;
            if ($request->type) $expense->type = $request->type;
            if ($from_web_portal) $expense->is_updated_by_super_admin = 1;
            $expense->save();

            if ($request['file']) $this->storeAttachment($expense, $request, $member);

            return ['expense' => ['id' => $expense->id]];
        } catch (Throwable $e) {
            return false;
        }
    }

    public function delete(Request $request, $expense)
    {
        try {
            $expense = Expense::find($expense);
            if (!$expense) return false;

            $expense->delete();

            return true;
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return false;
        }
    }

    public function storeAttachment(Expense $expense, Request $request, $member)
    {
        try {
            $data = $this->storeAttachmentToCDN($request->file('file'));
            return $expense->attachments()->save(new Attachment($this->withBothModificationFields($data)));
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            $sentry = app('sentry');
            $sentry->user_context(['request' => $request->all(), 'message' => $message]);
            $sentry->captureException($e);
            return false;
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return false;
        }
    }

    public function getAttachments(Expense $expense, Request $request)
    {
        try {
            if (!$expense) return false;
            $attachment = Attachment::where('attachable_type', get_class($expense))
                ->where('attachable_id', $expense->id)
                ->orderBy('created_at', 'DESC')
                ->select('id', 'title', 'file', 'file_type', 'created_at')
                ->get();
            return $attachment ? $attachment : false;
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return false;
        }
    }

    public function getExpenseByBusinessMember($business_members_ids)
    {
        return Expense::whereIn('business_member_id', $business_members_ids)->with([
            'businessMember' => function ($query) {
                $query->select('business_member.id', 'business_member.member_id', 'business_id', 'employee_id', 'business_role_id')->with([
                    'member' => function ($query) {
                        $query->select('members.id', 'members.profile_id')->with(['profile' => function ($query) {
                            $query->select('profiles.id', 'profiles.name');
                        }]);
                    },
                    'role' => function ($q) {
                        $q->select('business_roles.id', 'business_department_id', 'name')->with([
                            'businessDepartment' => function ($q) {
                                $q->select('business_departments.id', 'business_id', 'name');
                            }
                        ]);
                    }
                ]);
            }])->select('id', 'member_id', 'business_member_id', 'amount', 'type', 'created_at', DB::raw('YEAR(created_at) year, MONTH(created_at) month'), DB::raw('SUM(amount) amount'), DB::raw('YEAR(created_at) year, MONTH(created_at) month'))->groupby(['business_member_id', 'type']);
    }

    public function getDetailExpenseByBusinessMember($business_members_ids)
    {
        return Expense::whereIn('business_member_id', $business_members_ids)->with([
            'businessMember' => function ($query) {
                $query->select('business_member.id', 'business_member.member_id', 'business_id', 'business_member.employee_id', 'business_role_id')->with([
                    'member' => function ($query) {
                        $query->select('members.id', 'members.profile_id')->with(['profile' => function ($query) {
                            $query->select('profiles.id', 'profiles.name');
                        }]);
                    },
                    'role' => function ($q) {
                        $q->select('business_roles.id', 'business_department_id', 'name')->with([
                            'businessDepartment' => function ($q) {
                                $q->select('business_departments.id', 'business_id', 'name');
                            }
                        ]);
                    }
                ]);
            }]);
    }
}
