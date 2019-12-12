<?php namespace Sheba\Business\Announcement;


use App\Models\Business;
use Carbon\Carbon;
use Sheba\Dal\Announcement\Announcement;
use Sheba\Dal\Announcement\AnnouncementRepositoryInterface;

class Updater
{
    private $announcementRepository;
    private $title;
    private $shortDescription;
    private $data;
    /** @var Carbon */
    private $endDate;
    /** @var Announcement */
    private $announcement;

    public function __construct(AnnouncementRepositoryInterface $announcement_repository)
    {
        $this->announcementRepository = $announcement_repository;
        $this->data = [];
    }


    public function setTitle($title)
    {
        $this->title = $title;
        return $this;
    }

    public function setShortDescription($shortDescription)
    {
        $this->shortDescription = $shortDescription;
        return $this;
    }


    public function setEndDate($endDate)
    {
        $this->endDate = $endDate;
        return $this;
    }

    /**
     * @param Announcement $announcement
     * @return Updater
     */
    public function setAnnouncement($announcement)
    {
        $this->announcement = $announcement;
        return $this;
    }


    public function update()
    {
        $this->makeData();
        $this->announcementRepository->update($this->announcement, $this->data);
    }

    public function makeData()
    {
        if ($this->title) $this->data['title'] = $this->title;
        if ($this->shortDescription) $this->data['short_description'] = $this->shortDescription;
        if ($this->endDate) $this->data['end_date'] = $this->endDate->toDateTimeString();
    }
}