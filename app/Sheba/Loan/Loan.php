<?php

namespace Sheba\Loan;

use App\Models\BankUser;
use App\Models\PartnerBankLoan;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionException;
use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;
use Sheba\Loan\DS\BusinessInfo;
use Sheba\Loan\DS\Documents;
use Sheba\Loan\DS\FinanceInfo;
use Sheba\Loan\DS\NomineeGranterInfo;
use Sheba\Loan\DS\PartnerLoanRequest;
use Sheba\Loan\DS\PersonalInfo;
use Sheba\Loan\DS\RunningApplication;
use Sheba\Loan\Exceptions\AlreadyAssignToBank;
use Sheba\Loan\Exceptions\AlreadyRequestedForLoan;
use Sheba\Loan\Exceptions\InvalidStatusTransaction;
use Sheba\Loan\Exceptions\NotApplicableForLoan;
use Sheba\ModificationFields;

class Loan
{
    use CdnFileManager, FileManager, ModificationFields;
    private $repo;
    private $partner;
    private $data;
    private $profile;
    private $partnerLoanRequest;
    private $resource;
    private $personal;
    private $finance;
    private $business;
    private $nominee_granter;
    private $document;

    public function __construct()
    {
        $this->repo = new LoanRepository();
    }

    /**
     * @return mixed
     */
    public function getProfile()
    {
        return $this->profile;
    }

    /**
     * @param mixed $profile
     * @return Loan
     */
    public function setProfile($profile)
    {
        $this->profile = $profile;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @param mixed $resource
     * @return Loan
     */
    public function setResource($resource)
    {
        $this->resource = $resource;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed $data
     * @return Loan
     */
    public function setData($data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPartner()
    {
        return $this->partner;
    }

    /**
     * @param mixed $partner
     * @return Loan
     */
    public function setPartner($partner)
    {
        $this->partner = $partner;
        return $this;
    }

    public function get($id)
    {
        /** @var PartnerBankLoan $loan */
        $loan    = $this->repo->find($id);
        $request = new PartnerLoanRequest($loan);
        return $request->toArray();
    }

    public function update($loan_id, Request $request)
    {
        /** @var PartnerBankLoan $loan */

            $loan = $this->repo->find($loan_id);
            $loanRequest = (new PartnerLoanRequest($loan));
            $details = $loanRequest->details();
           // $new_data = json_decode($request->get('data'),true);
            $new_data = $request->get('data');
            $updater = (new Updater($details, $new_data));
            $updater->update($loanRequest, $request);
            $difference = $updater->findDifference()->getDifference();
            if (!empty($difference)) {
                $loanRequest->storeChangeLog($request->user, json_encode(array_column($difference, 'title')), json_encode(array_column($difference, 'old')), json_encode(array_column($difference, 'new')), 'Loan Request Updated');
            }

    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function homepage()
    {
        $running = !$this->partner->loan->isEmpty() ? $this->partner->loan->last()->toArray() : [];
        $data    = [
            'big_banner' => config('sheba.s3_url') . 'images/offers_images/banners/loan_banner_v3_1440_628.jpg',
            'banner'     => config('sheba.s3_url') . 'images/offers_images/banners/loan_banner_v3_720_324.jpg',
        ];
        $data    = array_merge($data, (new RunningApplication($running))->toArray());
        $data    = array_merge($data, ['details' => self::homepageStatics()]);
        return $data;
    }

    public static function homepageStatics()
    {
        return [
            [
                'title' => 'ব্যাংক লোনের সুবিধা কি কি - ',
                'list' => [
                    'সহজ শর্তে লোন নিন',
                    'জামানত বিহীন লোন নিন',
                    'ঘরে বসেই লোনের আবেদন করুন',
                    'ঘরে বসেই লোন পরিশোধ করুন'
                ],
                'list_icon' => 'icon'
            ],
            [
                'title' => 'ব্যাংক লোন কিভাবে নেবেন- ',
                'list' => [
                    'sManager অ্যাপ থেকে প্রয়োজনীয় সকল তথ্য পুরন করুন',
                    'লোন ক্যলকুলেটর দিয়ে হিসাব করে কিস্তির ধারনা নিন',
                    'লোনের আবেদন নিশ্চিত করুন',
                    'সেবা ও ব্যঙ্ক থেকে যাচাই করার পরে খুব দ্রুত আপনার কাছে লোন পৌঁছে যাবে'
                ],
                'list_icon' => 'number'
            ]
        ];
    }

    /**
     * @throws NotApplicableForLoan
     * @throws ReflectionException
     * @throws AlreadyRequestedForLoan
     */
    public function apply()
    {
        $this->validate();
        $data       = $this->data;
        $fields     = [
            'personal',
            'business',
            'finance',
            'nominee_granter',
            'document'
        ];
        $final_info = [];
        foreach ($fields as $val) {
            $final_info[$val] = $this->$val->toArray();
        }
        $data['final_information_for_loan'] = json_encode($final_info);
        return (new PartnerLoanRequest())->setPartner($this->partner)->create($data);
    }

    /**
     * @throws AlreadyRequestedForLoan
     * @throws NotApplicableForLoan
     * @throws ReflectionException
     */
    public function validate()
    {
        $requests = $this->repo->where('partner_id', $this->partner->id)->get();
        if (!$requests->isEmpty()) {
            $last_request = $requests->last();
            $statuses     = constants('LOAN_STATUS');
            if (in_array($last_request->status, [
                $statuses['approved'],
                $statuses['considerable']
            ])) {
                throw new AlreadyRequestedForLoan();
            }
        }
        $applicable = $this->getCompletion()['is_applicable_for_loan'];
        if (!$applicable)
            throw new NotApplicableForLoan();

    }

    /**
     * @return array
     * @throws ReflectionException
     */
    public function getCompletion()
    {
        $data                           = [
            'personal'  => $this->personalInfo()->completion(),
            'business'  => $this->businessInfo()->completion(),
            'finance'   => $this->financeInfo()->completion(),
            'nominee'   => $this->nomineeGranter()->completion(),
            'documents' => $this->documents()->completion()
        ];
        $data['is_applicable_for_loan'] = $this->isApplicableForLoan($data);
        return $data;
    }

    public function personalInfo()
    {
        $this->personal = (new PersonalInfo($this->partner, $this->resource, $this->partnerLoanRequest));
        return $this->personal;
    }

    public function businessInfo()
    {
        $this->business = (new BusinessInfo($this->partner, $this->resource));
        return $this->business;
    }

    public function financeInfo()
    {
        $this->finance = (new FinanceInfo($this->partner, $this->resource));
        return $this->finance;
    }

    public function nomineeGranter()
    {
        $this->nominee_granter = (new NomineeGranterInfo($this->partner, $this->resource));
        return $this->nominee_granter;
    }

    public function documents()
    {
        $this->document = (new Documents($this->partner, $this->resource));
        return $this->document;
    }

    private function isApplicableForLoan($data)
    {
        return Completion::isApplicableForLoan($data);
    }

    public function history()
    {
        $loans = $this->partner->loan;
        if ($loans->isEmpty())
            return [];
        $history = [];
        foreach ($loans as $loan) {
            $loanRequest = new PartnerLoanRequest($loan);
            $history[]   = $loanRequest->setPartner($this->partner)->history();
        }
        return $history;
    }

    public function all(Request $request)
    {

        $user    = $request->user;
        $bank_id = null;
        if ($user instanceof BankUser)
            $bank_id = $user->bank->id;
        $query = $this->repo;
        if ($bank_id) {
            $query = $query->where('partner_bank_loans.bank_id', $bank_id);
        }
        $data   = $query->with(['bank'])->get();
        $output = collect();

        foreach ($data as $loan) {
            $output->push((new PartnerLoanRequest($loan))->listItem());
        }
        $output = $output->sortByDesc('id');
        return $this->filterList($request, $output);
    }

    private function filterList(Request $request, Collection $output)
    {
        if ($request->has('q')) {
            $output = $output->filter(function ($item) use ($request) {
                $query = strtolower($request->q);
                return str_contains(strtolower($item['name']), $query) || str_contains($item['phone'], $query) || str_contains(strtolower($item['partner']), $query) || str_contains(strtolower($item['bank']['name']), $query);
            });
        }
        if ($request->has('date')) {
            $output = $output->filter(function ($item) use ($request) {
                $date      = Carbon::parse($request->date)->format('Y-m-d');
                $item_date = Carbon::parse($item->created_at)->format('Y-m-d');
                return $date == $item_date;
            });
        }
        return $output->values();
    }

    /**
     * @param $loan_id
     * @param $bank_id
     * @throws AlreadyAssignToBank
     */
    public function assignBank($loan_id, $bank_id)
    {
        $model = $this->repo->find($loan_id);
        if ($model->bank_id)
            throw new AlreadyAssignToBank();
        $this->repo->update($model, ['bank_id' => $bank_id]);
    }

    /**
     * @param $loan_id
     * @return array
     * @throws ReflectionException
     */
    public function show($loan_id)
    {
        /** @var PartnerBankLoan $request */
        $request = $this->repo->find($loan_id);
        return (new PartnerLoanRequest($request))->details();
    }

    /**
     * @param $loan_id
     * @param Request $request
     * @param $user
     * @throws ReflectionException
     */
    public function uploadDocument($loan_id, Request $request, $user)
    {
        /** @var PartnerBankLoan $loan */
        $loan           = $this->repo->find($loan_id);
        $picture        = $request->file('picture');
        $name           = $request->name;
        $formatted_name = strtolower(preg_replace("/ /", "_", $name));
        list($extra_file, $extra_file_name) = $this->makeExtraLoanFile($picture, $formatted_name);
        $url                                                                         = $this->saveImageToCDN($extra_file, getTradeLicenceImagesFolder(), $extra_file_name);
        $detail                                                                      = (new PartnerLoanRequest($loan))->details();
        $detail['final_information_for_loan']['document']['extras'][$formatted_name] = $url;
        $this->setModifier($user);
        DB::transaction(function () use ($loan, $detail, $formatted_name, $user, $name) {
            $loan->update($this->withUpdateModificationField([
                'final_information_for_loan' => json_encode($detail['final_information_for_loan'])
            ]));
            (new PartnerLoanRequest($loan))->storeChangeLog($user, 'extra_image', 'none', $formatted_name, $name);
        });

    }

    /**
     * @param $loan_id
     * @param Request $request
     * @throws InvalidStatusTransaction
     */
    public function statusChange($loan_id, Request $request)
    {

        $partner_bank_loan = $this->repo->find($loan_id);
        $old_status        = $partner_bank_loan->status;
        $new_status        = $request->new_status;
        $description       = $request->has('description') ? $request->description : 'Status Changed';
        $status            = [
            'applied',
            'submitted',
            'verified',
            'approved',
            'sanction_issued',
            'disbursed',
            'closed'
        ];
        $old_index         = array_search($old_status, $status);
        $new_index         = array_search($new_status, $status);
        if (!(($old_status == 'hold') || $new_index - $old_index == 1 || (in_array($new_status, [
                    'declined',
                    'hold',
                    'withdrawal'
                ]) && (!in_array($old_status, [
                    'disbursed',
                    'closed',
                    'declined',
                    'withdrawal'
                ]))))) {
            throw new InvalidStatusTransaction();
        }
        $partner_bank_loan->status = $new_status;
        DB::transaction(function () use ($partner_bank_loan, $request, $old_status, $new_status, $description) {
            $partner_bank_loan->update();
            (new PartnerLoanRequest($partner_bank_loan))->storeChangeLog($request->user, 'status', $old_status, $new_status, $description);
        });

    }
}
