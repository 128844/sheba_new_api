<?php namespace App\Transformers\Business;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;
use Sheba\Dal\BusinessShift\BusinessShift;

class ShiftDetailsTransformer extends TransformerAbstract
{
    public function transform(BusinessShift $shift)
    {
        return [
            'id' =>  $shift->id,
            'name' =>   $shift->name,
            'title' => $shift->title,
            'start_time' => Carbon::parse($shift->start_time)->format('h:i A'),
            'start_grace_time' => $shift->checkin_grace_time,
            'end_time' => Carbon::parse($shift->end_time)->format('h:i A'),
            'is_start_grace_enable' => $shift->checkin_grace_enable,
            'is_end_grace_enable' => $shift->checkout_grace_enable,
            'end_grace_time' => $shift->checkout_grace_time,
            'is_half_day_active' => $shift->is_halfday_enable,
            'assignments_count' => $shift->assignments_count,
            'created_at' => $shift->created_at->format('h:i A d/m/Y'),
            'updated_at' => $shift->updated_at->format('h:i A d/m/Y'),
            'created_by' => str_replace('Member-', '', $shift->created_by_name),
            'updated_by' => $shift->updated_by_name ? str_replace('Member-', '', $shift->updated_by_name) : null
        ];
    }
}
