<?php namespace App\Http\Controllers\B2b;

use App\Models\Business;
use Sheba\Gender\Gender;
use App\Transformers\Business\CoWorkerReportDetailsTransformer;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Intervention\Image\Image;
use League\Fractal\Resource\ResourceAbstract;
use Sheba\Business\CoWorker\Designations;
use Sheba\Business\CoWorker\Filter\CoWorkerInfoFilter;
use Sheba\Business\CoWorker\Requests\Requester as CoWorkerRequester;
use Sheba\Business\CoWorker\Excel as EmployeeExcel;
use App\Transformers\Business\CoWorkerDetailTransformer;
use Sheba\Business\CoWorker\Creator as CoWorkerCreator;
use Sheba\Business\CoWorker\Sorting\CoWorkerInfoSort;
use Sheba\Business\CoWorker\Updater as CoWorkerUpdater;
use App\Transformers\Business\CoWorkerListTransformer;
use Sheba\Business\CoWorker\Requests\EmergencyRequest;
use Sheba\Business\CoWorker\Requests\FinancialRequest;
use Sheba\Business\CoWorker\Requests\OfficialRequest;
use Sheba\Business\CoWorker\Requests\PersonalRequest;
use Sheba\Business\CoWorker\Requests\BasicRequest;
use App\Sheba\Business\Salary\Requester as CoWorkerSalaryRequester;
use Sheba\Business\CoWorker\Validation\CoWorkerExistenceCheck;
use Sheba\Dal\Salary\SalaryRepository;
use League\Fractal\Serializer\ArraySerializer;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;
use Sheba\Repositories\ProfileRepository;
use League\Fractal\Resource\Collection;
use Sheba\FileManagers\CdnFileManager;
use App\Transformers\CustomSerializer;
use Sheba\Business\CoWorker\Statuses;
use App\Repositories\FileRepository;
use App\Http\Controllers\Controller;
use Sheba\FileManagers\FileManager;
use App\Models\BusinessDepartment;
use League\Fractal\Resource\Item;
use Illuminate\Http\JsonResponse;
use App\Models\BusinessMember;
use Sheba\ModificationFields;
use App\Models\BusinessRole;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use Sheba\Business\CoWorker\SalaryCertificate\SalaryCertificateInfo;
use DB;

class CoWorkerController extends Controller
{
    use CdnFileManager, FileManager, ModificationFields;

    /** @var FileRepository $fileRepository */
    private $fileRepository;
    /** @var ProfileRepository $profileRepository */
    private $profileRepository;
    /** @var BasicRequest $basicRequest */
    private $basicRequest;
    /** @var EmergencyRequest $emergencyRequest */
    private $emergencyRequest;
    /** @var FinancialRequest $financialRequest */
    private $financialRequest;
    /** @var OfficialRequest $officialRequest */
    private $officialRequest;
    /** @var PersonalRequest $personalRequest */
    private $personalRequest;
    /** @var CoWorkerCreator $coWorkerCreator */
    private $coWorkerCreator;
    /** @var CoWorkerUpdater $coWorkerUpdater */
    private $coWorkerUpdater;
    /** @var CoWorkerRequester $coWorkerRequester */
    private $coWorkerRequester;
    /** @var CoWorkerSalaryRequester */
    private $coWorkerSalaryRequester;
    /** @var SalaryRepository */
    private $salaryRepository;
    /**  @var CoWorkerInfoFilter $coWorkerInfoFilter */
    private $coWorkerInfoFilter;
    /** @var CoWorkerInfoSort $coWorkerInfoSort */
    private $coWorkerInfoSort;
    /** @var BusinessMemberRepositoryInterface $businessMemberRepository */
    private $businessMemberRepository;
    /** @var CoWorkerExistenceCheck $coWorkerExistenceCheck */
    private $coWorkerExistenceCheck;

    /**
     * CoWorkerController constructor.
     * @param FileRepository $file_repository
     * @param ProfileRepository $profile_repository
     * @param BasicRequest $basic_request
     * @param EmergencyRequest $emergency_request
     * @param FinancialRequest $financial_request
     * @param OfficialRequest $official_request
     * @param PersonalRequest $personal_request
     * @param CoWorkerCreator $co_worker_creator
     * @param CoWorkerUpdater $co_worker_updater
     * @param CoWorkerRequester $coWorker_requester
     * @param CoWorkerSalaryRequester $co_worker_salary_requester
     * @param SalaryRepository $salary_repository
     * @param BusinessMemberRepositoryInterface $business_member_repo
     * @param CoWorkerInfoFilter $co_worker_info_filter
     * @param CoWorkerInfoSort $co_worker_info_sort
     * @param CoWorkerExistenceCheck $co_worker_existence_check
     */
    public function __construct(FileRepository         $file_repository, ProfileRepository $profile_repository, BasicRequest $basic_request,
                                EmergencyRequest       $emergency_request, FinancialRequest $financial_request,
                                OfficialRequest        $official_request, PersonalRequest $personal_request,
                                CoWorkerCreator        $co_worker_creator, CoWorkerUpdater $co_worker_updater,
                                CoWorkerRequester      $coWorker_requester, CoWorkerSalaryRequester $co_worker_salary_requester,
                                SalaryRepository       $salary_repository, BusinessMemberRepositoryInterface $business_member_repo,
                                CoWorkerInfoFilter     $co_worker_info_filter, CoWorkerInfoSort $co_worker_info_sort,
                                CoWorkerExistenceCheck $co_worker_existence_check)
    {
        $this->fileRepository = $file_repository;
        $this->profileRepository = $profile_repository;
        $this->basicRequest = $basic_request;
        $this->emergencyRequest = $emergency_request;
        $this->financialRequest = $financial_request;
        $this->officialRequest = $official_request;
        $this->personalRequest = $personal_request;
        $this->coWorkerCreator = $co_worker_creator;
        $this->coWorkerUpdater = $co_worker_updater;
        $this->coWorkerRequester = $coWorker_requester;
        $this->coWorkerSalaryRequester = $co_worker_salary_requester;
        $this->salaryRepository = $salary_repository;
        $this->coWorkerInfoFilter = $co_worker_info_filter;
        $this->coWorkerInfoSort = $co_worker_info_sort;
        $this->businessMemberRepository = $business_member_repo;
        $this->coWorkerExistenceCheck = $co_worker_existence_check;
    }

    /**
     * @param $business
     * @param Request $request
     * @return JsonResponse
     */
    public function index($business, Request $request)
    {
        /** @var Business $business */
        $business = $request->business;
        $is_payroll_enable = $business->payrollSetting->is_enable;
        $business_members = $business->getAllBusinessMember();
        list($offset, $limit) = calculatePagination($request);

        if ($request->has('for') && $request->for == 'prorate') {
            $department_info = $this->getEmployeeGroupByDepartment($business);
            return api_response($request, $department_info, 200, ['department_info' => $department_info]);
        }

        if ($request->has('department')) $business_members = $this->coWorkerInfoFilter->filterByDepartment($business_members, $request);
        if ($request->has('status')) $business_members = $this->coWorkerInfoFilter->filterByStatus($business_members, $request);
        $business_members = $business_members->with('salary');

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $employees = new Collection($business_members->get(), new CoWorkerListTransformer($is_payroll_enable));
        $employees_array = $manager->createData($employees)->toArray()['data'];
        usort($employees_array, function ($item1, $item2) {
            return $item1['show_alert'] < $item2['show_alert'];
        });
        $employees = collect($employees_array);
        $employees = $this->coWorkerInfoSort->sortCoworkerInList($employees, $request);
        $employees = $this->coWorkerInfoFilter->filterCoworkerInList($employees, $request);

        $total_employees = count($employees);
        $limit = $this->getLimit($request, $limit, $total_employees);
        $employees = collect($employees)->splice($offset, $limit);

        if (count($employees) > 0) return api_response($request, $employees, 200, [
            'employees' => $employees,
            'total_employees' => $total_employees
        ]);
        return api_response($request, null, 404);
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function show($business, $business_member_id, Request $request)
    {
        if (!is_numeric($business_member_id)) return api_response($request, null, 400);
        $business_member = $this->businessMemberRepository->find($business_member_id);
        if (!$business_member) return api_response($request, null, 404);

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $member = new Item($business_member, new CoWorkerDetailTransformer());
        $employee = $manager->createData($member)->toArray()['data'];

        if ($request->file === 'pdf') {
            return App::make('dompdf.wrapper')->loadView('pdfs.co_worker_details', compact('employee'))->download("co_worker_details.pdf");
        }

        if (count($employee) > 0) return api_response($request, $employee, 200, [
            'employee' => $employee,
            'business_member_id' => $business_member->id
        ]);
        return api_response($request, null, 404);
    }

    /**
     * basicInfoStore work as add new CoWorker
     * @param $business
     * @param Request $request
     * @return JsonResponse
     */
    public function basicInfoStore($business, Request $request)
    {
        $this->validate($request, [
            'first_name' => 'required|string',
            'email' => 'required|email',
            'department' => 'required|integer',
            'role' => 'required|string',
            'gender' => 'required|string|in:' . Gender::implodeEnglish(),
            'join_date' => 'required|date|date_format:Y-m-d'
        ]);

        $business = $request->business;
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);

        $email = $request->email;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return api_response($request, null, 420, ['email' => $email, 'message' => 'Invalid email address']);
        }

        $this->coWorkerExistenceCheck->setBusiness($business)->setEmail($email)->checkEmailUsability();
        if ($this->coWorkerExistenceCheck->hasError()) {
            return api_response($request, null, $this->coWorkerExistenceCheck->getErrorCode(), ['message' => $this->coWorkerExistenceCheck->getErrorMessage(), 'business_member_id' => $this->coWorkerExistenceCheck->getBusinessMemberId()]);
        }

        $this->basicRequest->setFirstName($request->first_name)
            ->setEmail($email)
            ->setDepartment($request->department)
            ->setRole($request->role)
            ->setGender($request->gender)
            ->setJoinDate($request->join_date)
            ->setGrossSalary($request->gross_salary);

        $this->coWorkerCreator->setBasicRequest($this->basicRequest)
            ->setBusiness($business)
            ->setStatus(Statuses::ACTIVE)
            ->setManagerMember($manager_member);

        $business_member = $this->coWorkerCreator->basicInfoStore();

        if (!$business_member) return api_response($request, null, 404);

        return api_response($request, null, 200, ['business_member_id' => $business_member->id]);
    }

    /**
     * basicInfoEdit work as update official info
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function basicInfoEdit($business, $business_member_id, Request $request)
    {
        $validation_data = [
            'first_name' => 'required|string',
            'department' => 'required|integer',
            'role' => 'required|string',
            'manager_employee' => 'sometimes|required',
            'join_date' => 'required|date|date_format:Y-m-d'
        ];
        $validation_data['pro_pic'] = $this->isFile($request->pro_pic) ? 'sometimes|required|mimes:jpg,jpeg,png,pdf' : 'sometimes|required|string';
        if (!$this->isNull($request->employee_type)) $validation_data['employee_type'] = 'sometimes|required|in:permanent,on_probation,contractual,intern';

        $email = $request->email;
        if (!$this->isNull($email)) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return api_response($request, null, 420, ['email' => $email, 'message' => 'Invalid email address']);
            }
            $validation_data['email'] = 'required|email|unique:profiles';
        }
        $this->validate($request, $validation_data);

        $business_member = $this->businessMemberRepository->find($business_member_id);
        $business = $request->business;
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);

        $this->coWorkerExistenceCheck->setBusiness($business)->setEmail($email)->checkEmailUsability();
        if ($this->coWorkerExistenceCheck->hasError()) {
            return api_response($request, null, $this->coWorkerExistenceCheck->getErrorCode(), ['message' => $this->coWorkerExistenceCheck->getErrorMessage(), 'business_member_id' => $this->coWorkerExistenceCheck->getBusinessMemberId()]);
        }

        $basic_request = $this->basicRequest->setProPic($request->pro_pic)
            ->setFirstName($request->first_name)
            ->setEmail($email)
            ->setDepartment($request->department)
            ->setRole($request->role)
            ->setJoinDate($request->join_date)
            ->setManagerEmployee($request->manager_employee)
            ->setEmployeeId($request->employee_id)
            ->setGrade($request->grade)
            ->setEmployeeType($request->employee_type);

        $this->coWorkerUpdater->setBasicRequest($basic_request)->setBusiness($business)->setBusinessMember($business_member);
        list($business_member, $profile_pic_name, $profile_pic) = $this->coWorkerUpdater->basicInfoUpdate();

        if ($business_member)
            return api_response($request, null, 200, ['profile_pic_name' => $profile_pic_name, 'profile_pic' => $profile_pic]);

        return api_response($request, null, 404);
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function personalInfoEdit($business, $business_member_id, Request $request)
    {
        $validation_data = [
            'gender' => 'required|string|in:' . Gender::implodeEnglish(),
            'mobile' => 'sometimes|required',
            'date_of_birth' => 'sometimes|required',
            'address' => 'sometimes|required',
            'nationality' => 'sometimes|required',
            'nid_number' => 'sometimes|required',
            'passport_no' => 'sometimes|required',
            'blood_group' => 'sometimes|required',
            'social_links' => 'sometimes|required',
        ];

        $validation_data['nid_front'] = $this->isFile($request->nid_front) ? 'sometimes|required|mimes:jpg,jpeg,png,pdf' : 'sometimes|required|string';
        $validation_data['nid_back'] = $this->isFile($request->nid_back) ? 'sometimes|required|mimes:jpg,jpeg,png,pdf' : 'sometimes|required|string';
        $validation_data['passport_image'] = $this->isFile($request->passport_image) ? 'sometimes|required|mimes:jpg,jpeg,png,pdf' : 'sometimes|required|string';
        $this->validate($request, $validation_data);

        $business_member = $this->businessMemberRepository->find($business_member_id);

        $business = $request->business;
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);
        if ($request->has('mobile') && $request->mobile != 'null') $request->mobile = formatMobile($request->mobile);

        $this->coWorkerExistenceCheck->setBusiness($business)->setBusinessMember($business_member)->setMobile($request->mobile)->isMobileNumberAlreadyTaken();
        if ($this->coWorkerExistenceCheck->hasError()) {
            return api_response($request, null, $this->coWorkerExistenceCheck->getErrorCode(), ['message' => $this->coWorkerExistenceCheck->getErrorMessage()]);
        }

        $personal_request = $this->personalRequest->setPhone($request->mobile)->setDateOfBirth($request->date_of_birth)
            ->setAddress($request->address)->setNationality($request->nationality)->setNidNumber($request->nid_number)
            ->setNidFront($request->nid_front)->setNidBack($request->nid_back)->setPassportNo($request->passport_no)
            ->setPassportImage($request->passport_image)->setGender($request->gender)->setSocialLinks($request->social_links)
            ->setBloodGroup($request->blood_group);

        $this->coWorkerUpdater->setPersonalRequest($personal_request)->setBusiness($business)->setBusinessMember($business_member);
        list($profile,
            $nid_image_front_name,
            $nid_image_front,
            $nid_image_back_name,
            $nid_image_back,
            $passport_image_name,
            $passport_image_link) = $this->coWorkerUpdater->personalInfoUpdate();

        if ($profile)
            return api_response($request, NULL, 200, [
                'nid_image_front_name' => $nid_image_front_name,
                'nid_image_front' => $nid_image_front,
                'nid_image_back_name' => $nid_image_back_name,
                'nid_image_back' => $nid_image_back,
                'passport_image_name' => $passport_image_name,
                'passport_image_link' => $passport_image_link
            ]);

        return api_response($request, null, 404);
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function financialInfoEdit($business, $business_member_id, Request $request)
    {
        $validation_data = [
            'tin_number ' => 'sometimes|required', 'bank_name ' => 'sometimes|required', 'bank_account_number ' => 'sometimes|required', 'bkash_number'=> 'sometimes|required'];
        $validation_data ['tin_certificate '] = $this->isFile($request->tin_certificate) ? 'sometimes|required|mimes:jpg,jpeg,png,pdf' : 'sometimes|required|string';
        $this->validate($request, $validation_data);

        $business_member = $this->businessMemberRepository->find($business_member_id);
        $manager_member = $request->manager_member;
        $business = $request->business;
        $this->setModifier($manager_member);

        $financial_request = $this->financialRequest->setTinNumber($request->tin_number)
            ->setTinCertificate($request->tin_certificate)
            ->setBankName($request->bank_name)
            ->setBankAccNumber($request->bank_account_number)
            ->setBkashNumber($request->bkash_number);

        list($profile, $image_name, $image_link) = $this->coWorkerUpdater
            ->setFinancialRequest($financial_request)
            ->setBusiness($business)
            ->setBusinessMember($business_member)
            ->financialInfoUpdate();

        if ($profile) return api_response($request, 1, 200, [
            'tin_certificate_name' => $image_name,
            'tin_certificate_link' => $image_link
        ]);
        return api_response($request, null, 404);
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function salaryInfoEdit($business, $business_member_id, Request $request)
    {
        $business_member = $this->businessMemberRepository->find($business_member_id);
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);
        $this->coWorkerSalaryRequester->setBusinessMember($business_member)
            ->setGrossSalary($request->gross_salary)
            ->setBreakdownPercentage($request->breakdown_percentage)
            ->setManagerMember($manager_member)
            ->createOrUpdate();
        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function emergencyInfoEdit($business, $business_member_id, Request $request)
    {
        $validation_data = [
            'name ' => 'sometimes|required',
            'mobile' => 'string',
            'relationship ' => 'sometimes|required'
        ];
        $validation_data['mobile'] = $this->isNull($request->mobile) ? 'string' : 'sometimes|string|mobile:bd';
        $this->validate($request, $validation_data);

        $business_member = $this->businessMemberRepository->find($business_member_id);
        $manager_member = $request->manager_member;
        $business = $request->business;
        $this->setModifier($manager_member);

        $emergency_request = $this->emergencyRequest->setEmergencyContractPersonName($request->name)
            ->setEmergencyContractPersonMobile($request->mobile)
            ->setRelationshipEmergencyContractPerson($request->relationship);

        $member = $this->coWorkerUpdater->setEmergencyRequest($emergency_request)->setBusiness($business)->setBusinessMember($business_member)->emergencyInfoUpdate();
        if ($member) return api_response($request, 1, 200);
        return api_response($request, null, 404);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getRoles(Request $request)
    {
        $business_department_ids = BusinessDepartment::query()->where('business_id', $request->business->id)->pluck('id')->toArray();
        $roles = BusinessRole::query()->whereIn('business_department_id', $business_department_ids)->pluck('name')->toArray();
        $designations_list = Designations::getDesignations();
        $all_roles = collect(array_merge($roles, $designations_list))->unique();
        if ($request->has('search')) {
            $all_roles = array_filter($all_roles->toArray(), function ($role) use ($request) {
                return str_contains(strtoupper($role), strtoupper($request->search));
            });
        }
        return api_response($request, $all_roles, 200, ['roles' => collect($all_roles)->values()]);
    }

    /**
     * @param $business
     * @param Request $request
     * @return JsonResponse
     */
    public function departmentRole($business, Request $request)
    {
        $business = $request->business;
        $business_departments = BusinessDepartment::published()->with([
            'businessRoles' => function ($q) {
                $q->select('id', 'name', 'business_department_id');
            }
        ])->where('business_id', $business->id)->select('id', 'business_id', 'name')->get();
        $departments = [];
        foreach ($business_departments as $business_dept) {
            $dept_role = collect();
            foreach ($business_dept->businessRoles as $role) {
                $role = ['id' => $role->id, 'name' => $role->name];
                $dept_role->push($role);
            }

            $department = [
                'id' => $business_dept->id,
                'name' => $business_dept->name,
                'roles' => $dept_role
            ];
            array_push($departments, $department);
        }
        if (count($departments) > 0)
            return api_response($request, $departments, 200, ['departments' => $departments]);

        return api_response($request, null, 404);
    }

    /**
     * @param $business
     * @param Request $request
     * @return JsonResponse
     */
    public function addBusinessDepartment($business, Request $request)
    {
        $this->validate($request, ['name' => 'required|string']);
        $business = $request->business;
        $member = $request->manager_member;
        $this->setModifier($member);

        $data = [
            'business_id' => $business->id,
            'name' => $request->name,
            'is_published' => 1
        ];
        BusinessDepartment::create($this->withCreateModificationField($data));

        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param Request $request
     * @return JsonResponse
     */
    public function getBusinessDepartments($business, Request $request)
    {
        $business = $request->business;
        $business_departments = BusinessDepartment::published()->where('business_id', $business->id)->select('id', 'business_id', 'name', 'created_at')->orderBy('id', 'DESC')->get();
        $departments = [];
        foreach ($business_departments as $business_department) {
            $department = [
                'id' => $business_department->id, 'name' => $business_department->name, 'created_at' => $business_department->created_at->format('d/m/y')
            ];
            array_push($departments, $department);
        }

        if (count($departments) > 0)
            return api_response($request, $departments, 200, ['departments' => $departments]);

        return api_response($request, null, 404);
    }

    /**
     * @param $business
     * @param Request $request
     * @return JsonResponse
     */
    public function addBusinessRole($business, Request $request)
    {
        $this->validate($request, ['name' => 'required|string', 'department_id' => 'required|integer']);
        $business = $request->business;
        $member = $request->manager_member;
        $this->setModifier($member);
        $data = [
            'business_department_id' => $request->department_id,
            'name' => trim($request->name),
            'is_published' => 1,
        ];
        BusinessRole::create($this->withCreateModificationField($data));

        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @return array
     */
    private function getEmployeeGroupByDepartment($business)
    {
        $business_departments = BusinessDepartment::published()->where('business_id', $business->id)
            ->select('id', 'business_id', 'name')->get();
        $department_info = [];
        foreach ($business_departments as $business_department) {
            $members = $business->membersWithProfileAndAccessibleBusinessMember();
            $members = $members->whereHas('businessMember', function ($q) use ($business_department) {
                $q->where('status', Statuses::ACTIVE)->whereHas('role', function ($q) use ($business_department) {
                    $q->whereHas('businessDepartment', function ($q) use ($business_department) {
                        $q->where('business_departments.id', $business_department->id);
                    });
                });
            });
            $members = $members->get()->unique();
            $employee_data = [];
            foreach ($members as $member) {
                $profile = $member->profile;
                $business_member = $member->businessMember;
                array_push($employee_data, [
                    'id' => $member->id,
                    'employee_id' => $business_member->employee_id,
                    'business_member_id' => $business_member->id,
                    'profile' => [
                        'id' => $profile->id,
                        'name' => $profile->name,
                        'pro_pic' => $profile->pro_pic,
                        'mobile' => $profile->mobile,
                        'email' => $profile->email,
                    ]
                ]);
            }
            array_push($department_info, [
                'department_id' => $business_department->id,
                'department' => $business_department->name,
                'employees' => $employee_data
            ]);
        }
        return $department_info;
    }

    /**
     * @param Request $request
     * @param $limit
     * @param $total_employees
     * @return mixed
     */
    private function getLimit(Request $request, $limit, $total_employees)
    {
        if ($request->has('limit') && $request->limit == 'all') return $total_employees;
        return $limit;
    }

    /**
     * @param $business
     * @param  Request  $request
     * @param  EmployeeExcel  $employee_report
     * @return null
     */
    public function downloadEmployeesReport($business, Request $request, EmployeeExcel $employee_report)
    {
        /** @var Business $business */
        $business = $request->business;
        $business_members = $business->getAllBusinessMember();

        if ($request->has('department')) $business_members = $this->coWorkerInfoFilter->filterByDepartment($business_members, $request);
        if ($request->has('status')) $business_members = $this->coWorkerInfoFilter->filterByStatus($business_members, $request);
        
        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $employees = new Collection($business_members->get(), new CoWorkerReportDetailsTransformer());
        $employees = collect($manager->createData($employees)->toArray()['data']);

        $employees = $this->coWorkerInfoSort->sortCoworkerInList($employees, $request);
        $employees = $this->coWorkerInfoFilter->filterCoworkerInList($employees, $request);

        $employees = collect($employees);

        return $employee_report->setEmployee($employees->toArray())->get();
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @param SalaryCertificateInfo $salary_certificate_info
     * @return JsonResponse
     */
    public function salaryCertificatePdf($business, $business_member_id, Request $request, SalaryCertificateInfo $salary_certificate_info)
    {
        if (!is_numeric($business_member_id)) return api_response($request, null, 400);
        $business_member = $this->businessMemberRepository->find($business_member_id);
        if (!$business_member) return api_response($request, null, 404);

        $salary_certificate_info = $salary_certificate_info->setBusinessMember($business_member)->get();

        if ($request->file == 'pdf') {
            return App::make('dompdf.wrapper')->loadView('pdfs.payroll.salary_certificate', compact('salary_certificate_info'))->download("salary_certificate.pdf");
        }

        return api_response($request, null, 200, ['salary_info_details' => $salary_certificate_info]);
    }

    /**
     * @param $file
     * @return bool
     */
    private function isFile($file)
    {
        if ($file instanceof Image || $file instanceof UploadedFile) return true;
        return false;
    }

    /**
     * @param $data
     * @return bool
     */
    private function isNull($data)
    {
        if ($data == 'null') return true;
        if ($data == null) return true;
        return false;
    }
}
