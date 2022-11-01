<?php namespace App\Transformers\Business;

use Carbon\Carbon;
use League\Fractal\TransformerAbstract;
use Sheba\Dal\BusinessShift\BusinessShift;

class ShiftListTransformer extends TransformerAbstract
{
    public function transform(BusinessShift $shift)
    {
        return [
            'id' => $shift->id,
            'name' => $shift->name,
            'title' => $shift->title,
            'start_time' => Carbon::parse($shift->start_time)->format('h:i A'),
            'end_time' => Carbon::parse($shift->end_time)->format('h:i A'),
            'is_half_day_active' => $shift->is_halfday_enable,
            'color' => $shift->color_code,
            'assignments_count' => $shift->assignments_count
        ];
    }
}
