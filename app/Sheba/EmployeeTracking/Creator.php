<?php namespace App\Sheba\EmployeeTracking;


use Illuminate\Support\Facades\DB;
use Sheba\Dal\Visit\Status;
use Sheba\Dal\Visit\VisitRepoImplementation;

class Creator
{
    /** @var VisitRepoImplementation $visitRepository*/
    private $visitRepository;

    public function __construct()
    {
        $this->visitRepository = app(VisitRepoImplementation::class);
    }

    /** @var Requester  $requester **/
    private $requester;
    private $visitData = [];

    public function setRequester(Requester $requester)
    {
        $this->requester = $requester;
        return $this;
    }

    public function create()
    {
        $this->makeData();
        DB::transaction(function () {
            $this->visitRepository->create($this->visitData);
        });
    }

    private function makeData()
    {
        $this->visitData = [
            'assignee_id' => $this->requester->getBusinessMember()->id,
            'visitor_id' => $this->requester->getEmployee(),
            'schedule_date' => $this->requester->getDate(),
            'title' => $this->requester->getTitle(),
            'description' => $this->requester->getDescription(),
            'status' => STATUS::CREATED,
        ];
    }

}