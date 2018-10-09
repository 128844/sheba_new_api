<?php

namespace Sheba\Partner;

use App\Models\Category;
use App\Models\Partner;
use App\Models\ResourceSchedule;
use App\Models\ScheduleSlot;
use Carbon\Carbon;
use DB;
use Illuminate\Support\Collection;

class PartnerScheduleSlot
{
    /** @var Partner */
    private $partner;
    /** @var Category */
    private $category;
    /** @var Carbon */
    private $today;
    /** @var Collection */
    private $shebaSlots;
    /** @var Collection */
    private $bookedSchedules;

    const SCHEDULE_START = '09:00:00';
    const SCHEDULE_END = '21:00:00';
    private $resources;

    public function __construct()
    {
        $this->shebaSlots = $this->getShebaSlots();
        $this->today = Carbon::now();
    }

    private function getShebaSlots()
    {
        return ScheduleSlot::select('start', 'end')
            ->where([
                ['start', '>=', DB::raw("CAST('" . self::SCHEDULE_START . "' As time)")],
                ['end', '<=', DB::raw("CAST('" . self::SCHEDULE_END . "' As time)")]
            ])->get();
    }

    public function setPartner($partner)
    {
        $this->partner = ($partner instanceof Partner) ? $partner : Partner::find($partner);
        return $this;
    }

    public function setCategory($category)
    {
        $this->category = ($category instanceof Category) ? $category : Category::find($category);
        return $this;
    }

    public function get($for_days = 14): array
    {
        $final = [];
        $this->resources = $this->getResources();
        $last_day = $this->today->copy()->addDays($for_days);
        $this->bookedSchedules = $this->getBookedSchedules($this->today->toDateTimeString() . ' 00:00:00', $last_day->format('Y-m-d') . ' 23:59:59');
        $day = $this->today->copy();
        while ($day <= $last_day) {
            $this->addAvailabilityToShebaSlots($day);
            array_push($final, ['value' => $day->toDateString(), 'slots' => $this->formatSlots($this->shebaSlots->toArray())]);
            $day->addDay();
        }
        return $final;
    }

    private function getResources()
    {
        return isset($this->category) ? $this->partner->resourcesInCategory($this->category->id) : $this->partner->handymanResources;
    }

    private function getBookedSchedules($start, $end)
    {
        return ResourceSchedule::whereIn('resource_id', $this->resources->pluck('id')->unique()->toArray())
            ->select('id', 'start', 'end', 'resource_id', DB::raw('Date(start) as schedule_date'))
            ->where('start', '>=', $start)
            ->where('end', '<=', $end)
            ->get();
    }

    private function addAvailabilityToShebaSlots(Carbon $day)
    {
        $this->addAvailabilityByWorkingInformation($day);
        $this->addAvailabilityByResource($day);
    }

    private function getWorkingDay(Carbon $day)
    {
        return $this->partner->workingHours->where('day', $day->format('l'))->first();
    }

    private function addAvailabilityByWorkingInformation(Carbon $day)
    {
        $working_day = $this->getWorkingDay($day);
        if ($working_day) {
            $date_string = $day->toDateString();
            $working_day_start_time = Carbon::parse($date_string . ' ' . $working_day->start_time);
            $working_day_end_time = Carbon::parse($date_string . ' ' . $working_day->end_time);
            $isToday = $day->isToday();
            foreach ($this->shebaSlots as $slot) {
                $slot_start_time = Carbon::parse($date_string . ' ' . $slot->start);
                if ($isToday && ($slot_start_time < $day)) {
                    $slot['is_available'] = 0;
                } else {
                    $slot['is_available'] = $slot_start_time->gte($working_day_start_time) && $slot_start_time->lte($working_day_end_time) ? 1 : 0;
                }
            }
        } else {
            $this->shebaSlots->each(function ($slot) {
                $slot['is_available'] = 0;
            });
        }
    }

    private function addAvailabilityByResource(Carbon $day)
    {
        $booked_schedules_group_by_date = $this->bookedSchedules->groupBy('schedule_date');
        $date_string = $day->toDateString();
        if ($bookedSchedules = $booked_schedules_group_by_date->get($date_string)) {
            $total_resources = $this->resources->count();
            foreach ($this->shebaSlots as $slot) {
                if (!$slot['is_available']) continue;
                $start_time = Carbon::parse($date_string . ' ' . $slot->start);
                $end_time = Carbon::parse($date_string . ' ' . $slot->end)->addMinutes($this->category->book_resource_minutes);
                $booked_resources = collect();
                foreach ($bookedSchedules as $booked_schedule) {
                    if ($booked_schedule->start->gte($start_time) || $booked_schedule->end->lte($end_time)) $booked_resources->push($booked_schedule->resource_id);
                }
                $slot['is_available'] = $total_resources > $booked_resources->unique()->count() ? 1 : 0;
            }
        }
    }

    private function formatSlots($slots)
    {
        foreach ($slots as &$slot) {
            $slot['key'] = $slot['start'] . '-' . $slot['end'];
            $slot['value'] = humanReadableShebaTime($slot['start']) . '-' . humanReadableShebaTime($slot['end']);
            unset($slot['start']);
            unset($slot['end']);
        }
        return $slots;
    }
}