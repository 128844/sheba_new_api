<?php namespace App\Http\Controllers\B2b;

use Illuminate\Http\JsonResponse;
use Sheba\Repositories\Interfaces\Business\DepartmentRepositoryInterface;
use App\Transformers\Business\BusinessDepartmentListTransformer;
use League\Fractal\Serializer\ArraySerializer;
use Sheba\Business\Department\CreateRequest;
use Sheba\Business\Department\UpdateRequest;
use League\Fractal\Resource\Collection;
use Sheba\Business\Department\Updater;
use Sheba\Business\Department\Creator;
use App\Http\Controllers\Controller;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use Throwable;

class DepartmentController extends Controller
{
    use ModificationFields;

    /** @var CreateRequest $departmentCreateRequest */
    private $departmentCreateRequest;
    /** @var Creator $creator */
    private $creator;
    /** @var UpdateRequest $departmentUpdateRequest */
    private $departmentUpdateRequest;
    /** @var Updater $updater */
    private $updater;
    /** @var DepartmentRepositoryInterface $departmentRepository */
    private $departmentRepository;

    public function __construct(CreateRequest $create_request, Creator $creator, UpdateRequest $update_request, Updater $updater, DepartmentRepositoryInterface $department_repository)
    {
        $this->departmentCreateRequest = $create_request;
        $this->creator = $creator;
        $this->departmentUpdateRequest = $update_request;
        $this->updater = $updater;
        $this->departmentRepository = $department_repository;
    }

    public function index($business, Request $request)
    {
        $business = $request->business;
        list($offset, $limit) = calculatePagination($request);
        $business_departments = $this->departmentRepository->getBusinessDepartmentByBusiness($business);
        if ($request->has('search')) $business_departments = $this->searchByDepartmentName($business_departments, $request);

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $business_departments = new Collection($business_departments, new BusinessDepartmentListTransformer());
        $business_departments = $manager->createData($business_departments)->toArray()['data'];


        if ($request->has('is_tagged') && $request->is_tagged == 1) {
            $business_departments = $this->filteredUntaggedDepartment($business_departments);
            if (count($business_departments) > 0)
                return api_response($request, $business_departments, 200, ['departments' => collect($business_departments)->values()]);
        }

        $total_business_departments = count($business_departments);
        if ($request->has('limit')) $business_departments = collect($business_departments)->splice($offset, $limit);

        if (count($business_departments) > 0) return api_response($request, $business_departments, 200, [
            'departments' => $business_departments,
            'total_business_departments' => $total_business_departments
        ]);
        return api_response($request, null, 404);
    }

    private function filteredUntaggedDepartment($business_departments)
    {
        return collect($business_departments)->filter(function ($department) {
            return $department['total_employee'] > 0;
        });
    }

    /**
     * @param $business
     * @param Request $request
     * @return JsonResponse
     */
    public function store($business, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'abbreviation' => 'required|string'
        ]);
        $business = $request->business;
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);
        $this->departmentCreateRequest->setBusiness($business)
            ->setDepartmentName($request->name)
            ->setAbbreviation($request->abbreviation);

        if ($this->departmentCreateRequest->hasError()) {
            return response()->json(['code' => $this->departmentCreateRequest->getErrorCode(), 'message' => $this->departmentCreateRequest->getErrorMessage()]);
        }
        $this->creator->setDepartmentCreateRequest($this->departmentCreateRequest)->create();
        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param $department
     * @param Request $request
     * @return JsonResponse
     */
    public function update($business, $department, Request $request)
    {
        $this->validate($request, [
            'name' => 'sometimes|required|string',
            'abbreviation' => 'sometimes|required|string'
        ]);

        $department = $this->departmentRepository->find($department);
        if (!$department) return api_response($request, null, 403);
        $business = $request->business;
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);
        $this->departmentUpdateRequest->setBusiness($business)
            ->setDepartmentName($request->name)
            ->setAbbreviation($request->abbreviation);
        if ($this->departmentUpdateRequest->hasError()) {
            return response()->json(['code' => $this->departmentUpdateRequest->getErrorCode(), 'message' => $this->departmentUpdateRequest->getErrorMessage()]);
        }
        $this->updater->setDepartmentUpdateRequest($this->departmentUpdateRequest)->setDepartment($department)->update();
        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param $department
     * @param Request $request
     * @return JsonResponse
     */
    public function destroy($business, $department, Request $request)
    {
        try {
            $department = $this->departmentRepository->find($department);
            if (!$department) return api_response($request, null, 403);
            $department->delete();
            return api_response($request, null, 200);
        } catch (Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    /**
     * @param $business_departments
     * @param Request $request
     * @return mixed
     */
    private function searchByDepartmentName($business_departments, Request $request)
    {
        return $business_departments->filter(function ($department) use ($request) {
            return str_contains(strtoupper($department->name), strtoupper($request->search));
        });
    }
}
