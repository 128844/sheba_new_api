<?php namespace App\Console\Commands;

use App\Models\Business;
use Carbon\CarbonPeriod;
use Sheba\Dal\ShiftAssignment\ShiftAssignmentRepository;

class ManualShiftAssignmentCreation extends Command
{
    protected $signature = 'sheba:create-shift-assignment {business_id} {start_date} {end_date}'; //Date Format: YYYY-MM-DD

    /** @var string The console command description. */
    protected $description = 'Create Missing Business Member Shift Assignment Table Data.';

    /*** @var ShiftAssignmentRepository $shiftAssignmentRepo*/
    private $shiftAssignmentRepo;

    public function __construct()
    {
        $this->shiftAssignmentRepo = app(ShiftAssignmentRepository::class);
        parent::__construct();
    }

    public function handle()
    {
        $start_date = $this->argument('start_date');
        $end_date = $this->argument('end_date');
        $business_id = $this->argument('business_id');
        /*** @var Business $business*/
        $business = Business::find($business_id);
        if ($business->is_shift_enable)
        {
            $businessMemberIds = $business->getActiveBusinessMember()->pluck('id')->toArray();
            
            $period = CarbonPeriod::create()->setDates($start_date, $end_date);
            foreach($businessMemberIds as $businessMemberId)
            {
                $data = [];
                foreach($period as $date)
                {
                    if ($this->shiftAssignmentRepo->where('business_member_id', $businessMemberId)->where('date', $date->toDateString())->exists()) continue;
                    $data[] = [
                        'business_member_id'    => $businessMemberId,
                        'date'                  => $date->toDateString(),
                        'is_general'            => 1
                    ];
                }
                $this->shiftAssignmentRepo->insert($data);
            }
        }
        
    }

}
