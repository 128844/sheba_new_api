<?php namespace App\Http\Controllers\B2b;

use Sheba\Gender\Gender;
use Carbon\Carbon;
use Sheba\Business\CoWorker\Requests\BasicRequest;
use Sheba\Business\CoWorker\Requests\Requester as CoWorkerRequester;
use Sheba\Reports\ExcelHandler;
use Sheba\Reports\Exceptions\NotAssociativeArray;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;
use Sheba\Business\CoWorker\Validation\CoWorkerExistenceCheck;
use Sheba\Business\CoWorker\Updater as CoWorkerUpdater;
use Sheba\FileManagers\CdnFileManager;
use Sheba\Business\CoWorker\Statuses;
use App\Repositories\FileRepository;
use App\Http\Controllers\Controller;
use Sheba\FileManagers\FileManager;
use Illuminate\Http\JsonResponse;
use App\Models\BusinessMember;
use Sheba\ModificationFields;
use Illuminate\Http\Request;
use Exception;

class CoWorkerStatusController extends Controller
{
    use CdnFileManager, FileManager, ModificationFields;

    /** @var FileRepository $fileRepository */
    private $fileRepository;
    /** @var BasicRequest $basicRequest */
    private $basicRequest;
    /** @var CoWorkerUpdater $coWorkerUpdater */
    private $coWorkerUpdater;
    /** @var CoWorkerRequester $coWorkerRequester */
    private $coWorkerRequester;
    /** @var BusinessMemberRepositoryInterface $businessMemberRepository */
    private $businessMemberRepository;
    /** @var CoWorkerExistenceCheck $coWorkerExistenceCheck */
    private $coWorkerExistenceCheck;

    /**
     * CoWorkerStatusController constructor.
     * @param FileRepository $file_repository
     * @param CoWorkerUpdater $co_worker_updater
     * @param BasicRequest $basic_request
     * @param CoWorkerRequester $coWorker_requester
     * @param BusinessMemberRepositoryInterface $business_member_repo
     * @param CoWorkerExistenceCheck $co_worker_existence_check
     */
    public function __construct(FileRepository $file_repository, CoWorkerUpdater $co_worker_updater, BasicRequest $basic_request,
                                CoWorkerRequester $coWorker_requester, BusinessMemberRepositoryInterface $business_member_repo,
                                CoWorkerExistenceCheck $co_worker_existence_check)
    {
        $this->fileRepository = $file_repository;
        $this->basicRequest = $basic_request;
        $this->coWorkerUpdater = $co_worker_updater;
        $this->coWorkerRequester = $coWorker_requester;
        $this->businessMemberRepository = $business_member_repo;
        $this->coWorkerExistenceCheck = $co_worker_existence_check;
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function statusUpdate($business, $business_member_id, Request $request)
    {
        $this->validate($request, ['status' => 'required|string|in:' . implode(',', Statuses::get())]);
        $requester_business_member = $request->business_member;
        if ($requester_business_member->id == $business_member_id) return api_response($request, null, 404, ['message' => 'Sorry, You cannot deactivated yourself as super admin.']);

        $business = $request->business;
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);

        $business_member = $this->businessMemberRepository->find($business_member_id);
        $coWorker_requester = $this->coWorkerRequester->setStatus($request->status);
        $this->coWorkerUpdater->setCoWorkerRequest($coWorker_requester)->setBusiness($business)->setBusinessMember($business_member);

        if ($this->isReInviteFeasible($business_member->status, $request->status)) {
            $this->coWorkerUpdater->reInvite();
            return api_response($request, 1, 200);
        }
        if ($this->isDeleteFeasible($business_member->status, $request->status)) {
            $this->coWorkerUpdater->delete();
            return api_response($request, 1, 200);
        }
        if ($this->isActive($request->status)) {
            $this->coWorkerExistenceCheck
                ->setBusiness($business)
                ->setBusinessMember($business_member)
                ->isActiveOrInvitedInAnotherBusiness();

            if ($this->coWorkerExistenceCheck->hasError()) {
                return api_response($request, null, $this->coWorkerExistenceCheck->getErrorCode(), [
                    'message' => $this->coWorkerExistenceCheck->getErrorMessage()
                ]);
            }
        }
        $business_member = $this->coWorkerUpdater->statusUpdate();
        if ($business_member) return api_response($request, 1, 200);

        return api_response($request, null, 404);
    }

    /**
     * @param $business
     * @param Request $request
     * @param ExcelHandler $excel_handler
     * @return JsonResponse
     * @throws NotAssociativeArray
     */
    public function bulkStatusUpdate($business, Request $request, ExcelHandler $excel_handler)
    {
        $this->validate($request, [
            'employee_ids' => "required",
            'status' => 'required|string|in:' . implode(',', Statuses::get())
        ]);
        $business = $request->business;
        $manager_member = $request->manager_member;
        $requester_business_member = $request->business_member;
        $this->setModifier($manager_member);
        $business_member_ids = json_decode($request->employee_ids);

        if (in_array($requester_business_member->id, $business_member_ids)) return api_response($request, null, 404, ['message' => 'One of the Ids contains superadmin ID, which cannot be deactivated, Please check again.']);
        $errors = [];
        foreach ($business_member_ids as $business_member_id) {
            $business_member = $this->businessMemberRepository->find($business_member_id);
            $coWorker_requester = $this->coWorkerRequester->setStatus($request->status);
            $this->coWorkerUpdater->setCoWorkerRequest($coWorker_requester)->setBusiness($business)->setBusinessMember($business_member);
            if ($this->isReInviteFeasible($business_member->status, $request->status)) $this->coWorkerUpdater->reInvite();
            if ($this->isDeleteFeasible($business_member->status, $request->status)) $this->coWorkerUpdater->delete();
            if ($business_member->status == $request->status) continue;
            if ($request->status == Statuses::DELETE) continue;

            if ($this->isActive($request->status)) {
                $this->coWorkerExistenceCheck->setBusiness($business)->setBusinessMember($business_member)
                    ->isActiveOrInvitedInAnotherBusiness()->isEssentialInfoAvailableForActivate();

                if ($this->coWorkerExistenceCheck->hasError()) {
                    array_push($errors, ['email' => $this->coWorkerExistenceCheck->getEmail(), 'message' => $this->coWorkerExistenceCheck->getErrorMessage()]);
                    $this->coWorkerExistenceCheck->resetError();
                    continue;
                }
            }

            $this->coWorkerUpdater->statusUpdate();
        }

        if ($errors) {
            $file_name = Carbon::now()->timestamp . "_co_worker_status_change_error_$business->id.xlsx";
            $file = $excel_handler->setName('Co worker Status Change')->setFilename($file_name)->setDownloadFormat('xlsx')->createReport($errors)->save();
            $file_path = $this->saveFileToCDN($file, getCoWorkerStatusChangeErrorFolder(), $file_name);
            unlink($file);

            if ($this->isFailedToChangeStatusAllCoworker($errors, $business_member_ids)) {
                return api_response($request, null, 422, [
                    'message' => 'Alert! Status Change failed',
                    'description' => "Download the excel file to see details",
                    'link' => $file_path
                ]);
            }

            return api_response($request, null, 303, [
                'message' => 'Alert! Some Status Change failed',
                'description' => "Download the excel file to see details",
                'link' => $file_path
            ]);
        }

        return api_response($request, null, 200);
    }

    /**
     * @param $business
     * @param $business_member_id
     * @param Request $request
     * @return JsonResponse
     */
    public function activeFromInvited($business, $business_member_id, Request $request)
    {
        $this->validate($request, [
            'name' => 'required|string',
            'gender' => 'required|string|in:' . Gender::implodeEnglish(),
            'department' => 'required|integer',
            'role' => 'required|string',
            'join_date' => 'required|date|date_format:Y-m-d',
            'status' => 'required|string|in:' . implode(',', Statuses::get())
        ]);
        $requester_business_member = $request->business_member;
        if ($requester_business_member->id == $business_member_id && $request->status != Statuses::ACTIVE) return api_response($request, null, 404, ['message' => 'Sorry, You cannot deactivated yourself as super admin.']);

        $business = $request->business;
        $manager_member = $request->manager_member;
        $this->setModifier($manager_member);

        $business_member = $this->businessMemberRepository->find($business_member_id);

        $basic_request = $this->basicRequest->setFirstName($request->name)
            ->setDepartment($request->department)
            ->setRole($request->role)
            ->setGender($request->gender)
            ->setStatus('active')
            ->setJoinDate($request->join_date)
            ->setGrossSalary($request->gross_salary);

        $this->coWorkerUpdater->setBasicRequest($basic_request)->setBusiness($business)->setBusinessMember($business_member);
        $business_member = $this->coWorkerUpdater->activeFormInviteOrInactive();

        if ($business_member) return api_response($request, 1, 200);

        return api_response($request, null, 404);
    }

    /**
     * @param $business_member_current_status
     * @param $requested_status
     * @return bool
     */
    private function isReInviteFeasible($business_member_current_status, $requested_status)
    {
        if ($business_member_current_status == Statuses::INVITED && $requested_status == Statuses::INVITED)
            return true;
        return false;
    }

    /**
     * @param $business_member_current_status
     * @param $requested_status
     * @return bool
     */
    private function isDeleteFeasible($business_member_current_status, $requested_status)
    {
        if ($business_member_current_status == Statuses::INVITED && $requested_status == Statuses::DELETE)
            return true;
        return false;
    }

    /**
     * @param $requested_status
     * @return bool
     */
    private function isActive($requested_status)
    {
        return $requested_status == Statuses::ACTIVE;
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

    private function isFailedToChangeStatusAllCoworker(array $errors, $business_member_ids)
    {
        return count($errors) == count($business_member_ids);
    }
}
