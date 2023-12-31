<?php namespace App\Http\Controllers\B2b;

use App\Models\Business;
use App\Models\BusinessMember;
use App\Sheba\Business\CoWorker\ManagerSubordinateEmployeeList;
use Sheba\Business\EmployeeTracking\EmployeeVisit\Excel as EmployeeVisitExcel;
use Sheba\Business\EmployeeTracking\MyVisit\Excel as MyVisitExcel;
use App\Transformers\Business\MyVisitListTransformer;
use App\Transformers\Business\TeamVisitListTransformer;
use App\Transformers\Business\VisitDetailsTransformer;
use App\Transformers\CustomSerializer;
use Illuminate\Http\JsonResponse;
use League\Fractal\Resource\Item;
use League\Fractal\Serializer\ArraySerializer;
use League\Fractal\Resource\Collection;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use League\Fractal\Manager;
use Sheba\Dal\Visit\VisitRepository;
use Illuminate\Support\Arr;
use Sheba\Helpers\TimeFrame;

class VisitController extends Controller
{
    /** @var VisitRepository $visitRepository */
    private $visitRepository;
    /** @var ManagerSubordinateEmployeeList $subordinateEmployeeList */
    private $subordinateEmployeeList;

    /**
     * @param VisitRepository $visit_repository
     * @param ManagerSubordinateEmployeeList $subordinate_employee_list
     */
    public function __construct(VisitRepository $visit_repository, ManagerSubordinateEmployeeList $subordinate_employee_list)
    {
        $this->visitRepository = $visit_repository;
        $this->subordinateEmployeeList = $subordinate_employee_list;
    }

    /**
     * @param Request $request
     * @param TimeFrame $time_frame
     * @param EmployeeVisitExcel $employee_visit_excel
     * @return JsonResponse
     */
    public function getTeamVisits(Request $request, TimeFrame $time_frame, EmployeeVisitExcel $employee_visit_excel)
    {
        /** @var Business $business */
        $business = $request->business;
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;

        list($offset, $limit) = calculatePagination($request);
        $visits = $this->visitRepository->getAllVisitsWithRelations()->where('visitor_id', '<>', $business_member->id)->orderBy('id', 'DESC');
        $visits = $visits->whereIn('visitor_id', $this->getBusinessMemberIds($business, $business_member));
        $show_empty_page = $visits->count() > 0 ? 0 : 1;

        /** Department Filter */
        if ($request->has('department_id')) {
            $visits = $visits->whereHas('visitor', function ($q) use ($request) {
                $q->whereHas('role', function ($q) use ($request) {
                    $q->whereHas('businessDepartment', function ($q) use ($request) {
                        $q->where('business_departments.id', $request->department_id);
                    });
                });
            });
        }

        /** Status Filter */
        if ($request->has('status')) {
            $visits = $visits->where('status', $request->status);
        }

        /** Month Filter */
        if ($request->has('start_date') && $request->has('end_date')) {
            $time_frame = $time_frame->forDateRange($request->start_date, $request->end_date);
            $visits = $visits->whereBetween('schedule_date', [$time_frame->start, $time_frame->end]);
        }

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $visits = new Collection($visits->get(), new TeamVisitListTransformer());
        $visits = collect($manager->createData($visits)->toArray()['data']);

        if ($request->has('search')) $visits = $this->multipleSearch($visits, $request);

        $visits = collect($visits);
        $total_visits = $visits->count();
        #$limit = $this->getLimit($request, $limit, $total_visits);
        if ($request->has('limit') && !$request->has('file')) $visits = $visits->splice($offset, $limit);

        if ($request->has('file') && $request->file == 'excel') return $employee_visit_excel->setEmployeeVisitData($visits->toArray())->get();

        return api_response($request, $visits, 200, [
            'employees' => $visits,
            'total_visits' => $total_visits,
            'show_empty_page' => $show_empty_page
        ]);
    }

    /**
     * @param Request $request
     * @param TimeFrame $time_frame
     * @param MyVisitExcel $my_visit_excel
     * @return JsonResponse
     */
    public function getMyVisits(Request $request, TimeFrame $time_frame, MyVisitExcel $my_visit_excel)
    {
        /** @var Business $business */
        $business = $request->business;
        /** @var BusinessMember $business_member */
        $business_member = $request->business_member;

        list($offset, $limit) = calculatePagination($request);
        $visits = $this->visitRepository->getAllVisitsWithRelations()->where('visitor_id', $business_member->id)->orderBy('id', 'DESC');
        $show_empty_page = $visits->count() > 0 ? 0 : 1;

        /** Status Filter */
        if ($request->has('status')) {
            $visits = $visits->where('status', $request->status);
        }

        /** Month Filter */
        if ($request->has('start_date') && $request->has('end_date')) {
            $time_frame = $time_frame->forDateRange($request->start_date, $request->end_date);
            $visits = $visits->whereBetween('schedule_date', [$time_frame->start, $time_frame->end]);
        }

        $manager = new Manager();
        $manager->setSerializer(new ArraySerializer());
        $visits = new Collection($visits->get(), new MyVisitListTransformer());
        $visits = collect($manager->createData($visits)->toArray()['data']);

        if ($request->has('search')) $visits = $this->searchWithVisitTitle($visits, $request);

        $total_visits = $visits->count();
        #$limit = $this->getLimit($request, $limit, $total_visits);
        if ($request->has('limit') && !$request->has('file')) $visits = $visits->splice($offset, $limit);

        if ($request->has('file') && $request->file == 'excel') return $my_visit_excel->setMyVisitData($visits->toArray())->get();

        return api_response($request, $visits, 200, [
            'employees' => $visits,
            'total_visits' => $total_visits,
            'show_empty_page' => $show_empty_page
        ]);
    }

    /**
     * @param $business
     * @param $visit
     * @param Request $request
     * @return JsonResponse|void
     */
    public function show($business, $visit, Request $request)
    {
        $visit = $this->visitRepository->find($visit);
        if (!$visit) return api_response($request, null, 404);

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Item($visit, new VisitDetailsTransformer());
        $visit = $manager->createData($resource)->toArray()['data'];

        if (count($visit) > 0) return api_response($request, $visit, 200, ['visit' => $visit]);
    }

    /**
     * @param Business $business
     * @param BusinessMember $business_member
     * @return array
     */
    private function getBusinessMemberIds(Business $business, BusinessMember $business_member)
    {
        if ($business_member->isSuperAdmin()) return $business->getActiveBusinessMember()->pluck('id')->toArray();
        $manager_subordinates = $this->subordinateEmployeeList->get($business_member);
        return Arr::pluck($manager_subordinates, 'id');
    }

    /**
     * @param $visits
     * @param Request $request
     * @return mixed
     */
    private function searchWithEmployeeName($visits, Request $request)
    {
        return $visits->filter(function ($visit) use ($request) {
            return str_contains(strtoupper($visit['profile']['name']), strtoupper($request->search));
        });
    }

    /**
     * @param $visits
     * @param Request $request
     * @return mixed
     */
    private function searchWithVisitTitle($visits, Request $request)
    {
        return $visits->filter(function ($visit) use ($request) {
            return str_contains(strtoupper($visit['title']), strtoupper($request->search));
        });
    }

    /**
     * @param $visits
     * @param Request $request
     * @return array
     */
    private function multipleSearch($visits, Request $request)
    {
        $visits = $visits->toArray();
        $employee_ids = array_filter($visits, function ($visit) use ($request) {
            return str_contains($visit['profile']['employee_id'], $request->search);
        });
        $employee_names = array_filter($visits, function ($visit) use ($request) {
            return str_contains(strtoupper($visit['profile']['name']), strtoupper($request->search));
        });
        $titles = array_filter($visits, function ($visit) use ($request) {
            return str_contains(strtoupper($visit['title']), strtoupper($request->search));
        });

        $searched_results = collect(array_merge($employee_ids, $employee_names, $titles));
        $searched_results = $searched_results->unique(function ($search) {
            return $search['id'];
        });
        return $searched_results->values()->all();
    }
}