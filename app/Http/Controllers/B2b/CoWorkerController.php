<?php namespace App\Http\Controllers\B2b;

use App\Jobs\SendBusinessRequestEmail;
use App\Models\BusinessDepartment;
use App\Models\BusinessMember;
use App\Models\BusinessRole;
use App\Models\BusinessTrip;
use App\Models\Driver;
use App\Models\Profile;
use App\Repositories\FileRepository;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Controller;
use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use App\Models\Member;
use Carbon\Carbon;
use DB;
use Sheba\Repositories\ProfileRepository;

class CoWorkerController extends Controller
{
    use CdnFileManager, FileManager;
    use ModificationFields;

    private $fileRepository;
    private $profileRepository;

    public function __construct(FileRepository $file_repository, ProfileRepository $profile_repository)
    {
        $this->fileRepository = $file_repository;
        $this->profileRepository = $profile_repository;
    }

    public function store($business, Request $request)
    {
        try {
            $this->validate($request, [
                'name' => 'required|string',
                'mobile' => 'required|string|mobile:bd',
                'email' => 'required|email',
                'role' => 'required|integer',
                #'pro_pic' => 'required|mimes:jpeg,png',
                #'dob' => 'required|date|date_format:Y-m-d|before:' . Carbon::today()->format('Y-m-d'),
                #'address' => 'required|string',
                #'department' => 'required|integer',

            ]);
            $business = $request->business;
            $member = $request->manager_member;
            $this->setModifier($member);

            $profile = $this->profileRepository->checkExistingProfile($request->mobile, $request->email);
            $co_member = collect();
            if (!$profile) {
                $profile = $this->createProfile($member, $request);
                $new_member = $this->makeMember($profile);
                $co_member->push($new_member);

                $business = $member->businesses->first();
                $member_business_data = [
                    'business_id' => $business->id,
                    'member_id' => $co_member->first()->id,
                    'type' => 'Admin',
                    'join_date' => Carbon::now(),
                    #'department' => $request->department,
                    'business_role_id' => $request->role,
                ];
                BusinessMember::create($this->withCreateModificationField($member_business_data));
            } else {
                $old_member = $profile->member;
                if (!$old_member) {
                    $new_member = $this->makeMember($profile);
                    $co_member->push($new_member);
                } else {
                    $co_member->push($old_member);
                }

                $business = $member->businesses->first();
                $member_business_data = [
                    'business_id' => $business->id,
                    'member_id' => $co_member->first()->id,
                    'type' => 'Admin',
                    'join_date' => Carbon::now(),
                    #'department' => $request->department,
                    'business_role_id' => $request->role,
                ];
                BusinessMember::create($this->withCreateModificationField($member_business_data));
            }
            return api_response($request, $profile, 200, ['co_worker' => $co_member->first()->id]);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function index($business, Request $request)
    {
        try {
            $business = $request->business;
            $member = $request->manager_member;
            $this->setModifier($member);
            $members = $business->members();

            if ($request->has('department')) {
                $members->where(function ($query) use ($request) {
                    $query->whereHas('businessMember.role.businessDepartment', function ($query) use ($request) {
                        $query->where('name', $request->department);
                    });
                });
            }
            $members = $members->get()->unique();
            $employees = [];
            foreach ($members as $member) {
                $profile = $member->profile;
                $role = $member->businessMember->role;

                $employee = [
                    'id' => $member->id,
                    'name' => $profile->name,
                    'mobile' => $profile->mobile,
                    'email' => $profile->email,
                    'department' => $role ? $role->businessDepartment->name : null,
                ];
                array_push($employees, $employee);
            }
            if (count($employees) > 0) return api_response($request, $employees, 200, ['employees' => $employees]);
            else  return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function show($business, $employee, Request $request)
    {
        try {
            $business = $request->business;
            $member = Member::find((int)$employee);
            if (!$member) return api_response($request, null, 404);
            $profile = $member->profile;
            $employee = [
                'name' => $profile->name,
                'mobile' => $profile->mobile,
                'email' => $profile->email,
                'pro_pic' => $profile->pro_pic,
                'dob' => Carbon::parse($profile->dob)->format('M j, Y'),
                'designation' => $member->businessMember->role ? $member->businessMember->role->name : null,
            ];

            if (count($employee) > 0) return api_response($request, $employee, 200, ['employee' => $employee]);
            else  return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function departmentRole($business, Request $request)
    {
        try {
            $business = $request->business;
            $business_depts = BusinessDepartment::with(['businessRoles' => function ($q) {
                $q->select('id', 'name', 'business_department_id');
            }])->where('business_id', $business->id)->select('id', 'business_id', 'name')->get();
            $departments = [];
            foreach ($business_depts as $business_dept) {
                $dept_role = collect();
                foreach ($business_dept->businessRoles as $role) {
                    $role = [
                        'id' => $role->id,
                        'name' => $role->name,
                    ];
                    $dept_role->push($role);
                }

                $department = [
                    'id' => $business_dept->id,
                    'name' => $business_dept->name,
                    'roles' => $dept_role
                ];
                array_push($departments, $department);
            }
            if (count($departments) > 0) return api_response($request, $departments, 200, ['departments' => $departments]);
            else  return api_response($request, null, 404);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function addBusinessDepartment($business, Request $request)
    {
        try {
            $this->validate($request, [
                'name' => 'required|string',
                #'is_published' => 'required|boolean',

            ]);
            $business = $request->business;
            $member = $request->manager_member;
            $this->setModifier($member);
            $data = [
                'business_id' => $business->id,
                'name' => $request->name,
                'is_published' => 1
            ];
            $business_dept = BusinessDepartment::create($this->withCreateModificationField($data));
            return api_response($request, null, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function addBusinessRole($business, Request $request)
    {
        try {
            $this->validate($request, [
                'name' => 'required|string',
                'business_department_id' => 'required|integer',

            ]);
            $business = $request->business;
            $member = $request->manager_member;
            $this->setModifier($member);
            $data = [
                'business_department_id' => $request->business_department_id,
                'name' => $request->name,
                'is_published' => 1
            ];
            $business_role = BusinessRole::create($this->withCreateModificationField($data));
            return api_response($request, null, 200);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->validator->errors()->all());
            return api_response($request, $message, 400, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    private function createProfile($member, Request $request)
    {
        $this->setModifier($member);
        $profile_data = [
            'remember_token' => str_random(255),
            'mobile' => !empty($request->mobile) ? formatMobile($request->mobile) : null,
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt('sheba#test1')
            ##'gender' => $request->gender,
            #'dob' => $request->dob,
            #'nid_no' => $request->nid_no,
            #'pro_pic' => $this->updateProfilePicture('pro_pic', $request->file('pro_pic')),
            #'address' => $request->address,
            #'driver_id' => $driver->id,
        ];
        $profile = Profile::create($this->withCreateModificationField($profile_data));
        dispatch(new SendBusinessRequestEmail($request->email));
        return $profile;
    }

    private function makeMember($profile)
    {
        $this->setModifier($profile);
        $member = new Member();
        $member->profile_id = $profile->id;
        $member->remember_token = str_random(255);
        $member->save();
        return $member;
    }
}