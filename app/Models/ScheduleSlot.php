<?php namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use DB;

class ScheduleSlot extends Model
{
    protected $guarded = ['id'];
    const SCHEDULE_START = '09:00:00';
    const SCHEDULE_END = '21:00:00';

    public function scopeShebaSlots($q)
    {
        return $q->where([['start', '>=', DB::raw("CAST('" . self::SCHEDULE_START . "' As time)")], ['end', '<=', DB::raw("CAST('" . self::SCHEDULE_END . "' As time)")]]);
    }
}
