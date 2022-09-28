<?php namespace App\Http\Controllers\Employee;

use App\Http\Controllers\Controller;
use App\Sheba\Business\CoWorker\ManagerSubordinateEmployeeList;
use App\Sheba\EmployeeTracking\Creator;
use App\Sheba\EmployeeTracking\Requester;
use App\Sheba\EmployeeTracking\Updater;
use App\Transformers\Business\AppVisitDetailsTransformer;
use App\Transformers\Business\TeamVisitsTransformer;
use App\Transformers\Business\VisitHistoryTransformer;
use App\Transformers\CustomSerializer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Sheba\Business\BusinessBasicInformation;
use Illuminate\Support\Facades\DB;
use League\Fractal\Manager;
use League\Fractal\Resource\Collection;
use League\Fractal\Resource\Item;
use Sheba\Dal\Visit\Status;
use Sheba\Dal\Visit\VisitRepository;
use Sheba\Dal\VisitPhoto\VisitPhotoRepository;
use Sheba\Helpers\TimeFrame;
use Sheba\ModificationFields;
use Sheba\Business\EmployeeTracking\Visit\VisitList;
use Sheba\Business\EmployeeTracking\Visit\NoteCreator;
use Sheba\Business\EmployeeTracking\Visit\PhotoCreator;
use Sheba\Business\EmployeeTracking\Visit\StatusUpdater;

class VisitController extends Controller
{
    use BusinessBasicInformation, ModificationFields;

    /** @var VisitRepository $visitRepository */
    private $visitRepository;

    /**
     * @param VisitRepository $visit_repository
     */
    public function __construct(VisitRepository $visit_repository)
    {
        $this->visitRepository = $visit_repository;
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function getManagerSubordinateEmployeeList(Request $request)
    {
        $business_member = $request->business_member;
        $managers_data = (new ManagerSubordinateEmployeeList())->get($business_member, true, true);
        $departments = array_keys($managers_data);

        return api_response($request, null, 200, ['employee_list' => $managers_data, 'departments' => $departments]);
    }

    /**
     * @param Request $request
     * @param Requester $requester
     * @param Creator $creator
     * @return JsonResponse
     */
    public function create(Request $request, Requester $requester, Creator $creator)
    {
        $this->validate($request, [
            'date' => 'required|date_format:Y-m-d',
            'employee' => 'numeric',
            'title' => 'required|string',
            'description' => 'sometimes|required|string',
        ]);
        $business_member = $request->business_member;
        $member = $this->getMember($request);
        $this->setModifier($member);
        $requester->setBusinessMember($business_member)->setDate($request->date)
            ->setEmployee($request->employee)->setTitle($request->title)->setDescription($request->description);
        $creator->setRequester($requester)->create();
        return api_response($request, null, 200);
    }

    /**
     * @param $visit_id
     * @param Request $request
     * @param Requester $requester
     * @param Updater $updater
     * @return JsonResponse
     */
    public function update($visit_id, Request $request, Requester $requester, Updater $updater)
    {
        $this->validate($request, [
            'employee' => 'numeric',
            'title' => 'required|string',
            'description' => 'sometimes|required|string',
        ]);
        $business_member = $request->business_member;
        $employee_visit = $this->visitRepository->find($visit_id);
        if (!$employee_visit) return api_response($request, null, 404);
        $member = $this->getMember($request);
        $this->setModifier($member);
        $requester->setBusinessMember($business_member)->setEmployeeVisit($employee_visit)
            ->setEmployee($request->employee)->setTitle($request->title)->setDescription($request->description);
        $updater->setRequester($requester)->update();
        return api_response($request, null, 200);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function ownOngoingVisits(Request $request)
    {
        $business_member = $request->business_member;
        $own_visits = $this->visitRepository->where('visitor_id', $business_member->id)
            ->whereNotIn('status', [Status::COMPLETED, Status::CANCELLED])
            ->select('id', 'title', 'status', 'schedule_date')
            ->orderBy('id', 'desc')->get();
        if (count($own_visits) == 0) return api_response($request, null, 404);
        $own_visits->map(function (&$own_visit) {
            $own_visit['date'] = Carbon::parse($own_visit->schedule_date)->format('M d, Y');
            return $own_visit;
        });
        return api_response($request, $own_visits, 200, ['own_visits' => $own_visits]);
    }

    /**
     * @param Request $request
     * @param VisitList $visit_list
     * @return JsonResponse
     */
    public function ownVisitHistory(Request $request, VisitList $visit_list)
    {
        $business_member = $request->business_member;
        $own_visits = $this->visitRepository->where('visitor_id', $business_member->id)
            ->whereIn('status', [Status::COMPLETED, Status::CANCELLED])
            ->select('id', 'title', 'status', 'start_date_time', 'end_date_time', 'total_time_in_minutes', 'schedule_date', DB::raw('YEAR(schedule_date) year, MONTH(schedule_date) month'))
            ->orderBy('id', 'desc')->get();
        if (count($own_visits) == 0) return api_response($request, null, 404);
        $own_visits = $own_visits->groupBy('year')->transform(function ($item, $k) {
            return $item->groupBy('month');
        });

        $visit_history = $visit_list->getOwnVisitHistory($own_visits);
        return api_response($request, $own_visits, 200, ['own_visit_history' => $visit_history]);
    }

    public function ownVisitHistoryV2(Request $request)
    {
        $business_member = $request->business_member;
        list($offset, $limit) = calculatePagination($request);
        $own_visits = $this->visitRepository->where('visitor_id', $business_member->id)
            ->whereIn('status', [Status::COMPLETED, Status::CANCELLED])
            ->select('id', 'title', 'status', 'start_date_time', 'end_date_time', 'total_time_in_minutes', 'schedule_date')
            ->orderBy('schedule_date', 'desc')->get();
        if (count($own_visits) == 0) return api_response($request, null, 404);
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Collection($own_visits, new VisitHistoryTransformer());
        $own_visits = $manager->createData($resource)->toArray()['data'];
        $total_visit = count($own_visits);
        $own_visits = collect($own_visits)->splice($offset, $limit);
        return api_response($request, $own_visits, 200, ['total_visit' => $total_visit, 'own_visit_history' => $own_visits]);
    }

    /**
     * @param Request $request
     * @param VisitList $visit_list
     * @param TimeFrame $time_frame
     * @return JsonResponse
     */
    public function teamVisitsList(Request $request, VisitList $visit_list, TimeFrame $time_frame)
    {
        $business_member = $request->business_member;

        $team_visits = $visit_list->getTeamVisits($this->visitRepository, $business_member);

        if ($request->has('start_date') && $request->has('end_date')) {
            $time_frame = $time_frame->forDateRange($request->start_date, $request->end_date);
            $team_visits = $team_visits->whereBetween('schedule_date', [$time_frame->start, $time_frame->end]);
        }

        if ($request->has('employees')) {
            $team_visits = $team_visits->whereIn('visitor_id', json_decode($request->employees, 1));
        }

        if ($request->has('status')) {
            $team_visits = $team_visits->where('status', $request->status);
        }

        $team_visits = $team_visits->get();

        if (count($team_visits) == 0) return api_response($request, null, 404);
        $team_visits = $team_visits->groupBy('date');
        $team_visit_list = $visit_list->getTeamVisitList($team_visits);

        return api_response($request, $team_visit_list, 200, ['team_visit_list' => $team_visit_list]);
    }

    /**
     * @param Request $request
     * @param VisitList $visit_list
     * @param TimeFrame $time_frame
     * @return JsonResponse
     */
    public function teamVisitsListV2(Request $request, VisitList $visit_list, TimeFrame $time_frame)
    {
        $business_member = $request->business_member;
        list($offset, $limit) = calculatePagination($request);

        $team_visits = $visit_list->getTeamVisits($this->visitRepository, $business_member);

        if ($request->has('start_date') && $request->has('end_date')) {
            $time_frame = $time_frame->forDateRange($request->start_date, $request->end_date);
            $team_visits = $team_visits->whereBetween('schedule_date', [$time_frame->start, $time_frame->end]);
        }

        if ($request->has('employees')) {
            $team_visits = $team_visits->whereIn('visitor_id', json_decode($request->employees, 1));
        }

        if ($request->has('status')) {
            $team_visits = $team_visits->where('status', $request->status);
        }

        $team_visits = $team_visits->get();
        if (count($team_visits) == 0) return api_response($request, null, 404);
        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Collection($team_visits, new TeamVisitsTransformer());
        $team_visits = $manager->createData($resource)->toArray()['data'];
        $total_visit = count($team_visits);
        $team_visits = collect($team_visits)->splice($offset, $limit);
        return api_response($request, $team_visits, 200, ['total_visit' => $total_visit, 'team_visit_list' => $team_visits]);
    }

    /**
     * @param Request $request
     * @param $visit
     * @return JsonResponse
     */
    public function show(Request $request, $visit)
    {
        $business_member = $request->business_member;

        $visit = $this->visitRepository->find($visit);
        if (!$visit) return api_response($request, null, 404);

        $manager = new Manager();
        $manager->setSerializer(new CustomSerializer());
        $resource = new Item($visit, new AppVisitDetailsTransformer());
        $visit = $manager->createData($resource)->toArray()['data'];

        return api_response($request, $visit, 200, ['visit' => $visit]);
    }

    /**
     * @param Request $request
     * @param $visit
     * @param NoteCreator $note_creator
     * @return JsonResponse
     */
    public function storeNote(Request $request, $visit, NoteCreator $note_creator)
    {
        $this->validate($request, [
            'date' => 'required|date_format:Y-m-d',
            'note' => 'required|string',
            'status' => 'required|string'
        ]);
        $member = $this->getMember($request);
        $this->setModifier($member);

        $visit = $this->visitRepository->find($visit);
        if (!$visit) return api_response($request, null, 404);
        $note_creator->setVisit($visit)->setDate($request->date)
            ->setNote($request->note)->setStatus($request->status)->store();
        return api_response($request, null, 200);
    }

    /**
     * @param Request $request
     * @param $visit
     * @param PhotoCreator $photo_creator
     * @return JsonResponse
     */
    public function storePhoto(Request $request, $visit, PhotoCreator $photo_creator)
    {
        $business_member = $request->business_member;
        $this->validate($request, [
            'image' => 'required|file',
        ]);
        $member = $this->getMember($request);
        $this->setModifier($member);

        $visit = $this->visitRepository->find($visit);
        if (!$visit) return api_response($request, null, 404);

        $existing_photos_count = $visit->visitPhotos()->get()->count();
        if ($existing_photos_count > 5) return api_response($request, null, 420);

        $photo_creator->setVisit($visit)->setPhoto($request->image)->store();
        $photos = $visit->visitPhotos()->orderBy('id', 'DESC')->get()->map(function ($photo) {
            return [
                'id' => $photo->id,
                'photo' => $photo->photo
            ];
        })->toArray();
        return api_response($request, null, 200, ['photos' => $photos]);
    }


    /**
     * @param Request $request
     * @param $visit
     * @param StatusUpdater $status_updater
     * @return JsonResponse
     */
    public function updateStatus(Request $request, $visit, StatusUpdater $status_updater)
    {
        $validation_data = [
            'status' => 'required|string|in:' . implode(',', Status::get()),
            'lat' => 'required|numeric',
            'lng' => 'required|numeric'
        ];

        $business_member = $request->business_member;

        $visit = $this->visitRepository->find($visit);
        if (!$visit) return api_response($request, null, 404);

        if ($request->status === Status::STARTED) {
            $is_less_than_schedule_time = Carbon::now()->toDateString() < $visit->schedule_date->toDateString();
            if ($is_less_than_schedule_time) {
                return api_response($request, null, 420, ['msg' => 'You can not start visit before schedule time']);
            }
            $current_visits = $this->visitRepository->where('visitor_id', $business_member->id)
                ->whereIn('status', [Status::STARTED, Status::REACHED])->get()->count();
            if ($current_visits > 0) {
                return api_response($request, null, 420);
            }
        }

        if ($request->status === Status::RESCHEDULED) {
            $validation_data += ['note' => 'string', 'date' => 'required|date_format:Y-m-d'];
        }
        if ($request->status === Status::CANCELLED) {
            $validation_data += ['note' => 'string|required'];
        }
        $this->validate($request, $validation_data);
        $member = $this->getMember($request);
        $this->setModifier($member);

        $status_updater->setVisit($visit)->setStatus($request->status)->setLat($request->lat)->setLng($request->lng)
            ->setNote($request->note)->setDate($request->date)->update();
        return api_response($request, null, 200);
    }

    /**
     * @param $visit
     * @param $visit_photo
     * @param Request $request
     * @param VisitPhotoRepository $visit_photo_repository
     * @return JsonResponse
     */
    public function deletePhoto($visit, $visit_photo, Request $request, VisitPhotoRepository $visit_photo_repository)
    {
        $business_member = $request->business_member;
        $visit_photo = $visit_photo_repository->find($visit_photo);
        if (!$visit_photo) return api_response($request, null, 404);
        $visit_photo->delete();
        return api_response($request, null, 200);
    }

}
