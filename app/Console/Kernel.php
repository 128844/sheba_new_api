<?php namespace App\Console;

use App\Console\Commands\GeneratePayslip;
use App\Console\Commands\LeaveAdjustmentOnEndOfFiscalYear;
use App\Console\Commands\ManualShiftAssignmentCreation;
use App\Console\Commands\Payslip;
use App\Console\Commands\ProductUpload;
use App\Console\Commands\RunAnnouncementNotifications;
use App\Console\Commands\SetReleaseVersion;
use App\Console\Commands\TestCommand;
use App\Console\Commands\TopUpTestCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Sheba\Algolia\AlgoliaSync;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        ProductUpload::class,
        SetReleaseVersion::class,
        AlgoliaSync::class,
        TopUpTestCommand::class,
        Payslip::class,
        TestCommand::class,
        GeneratePayslip::class,
        LeaveAdjustmentOnEndOfFiscalYear::class,
        RunAnnouncementNotifications::class,
        ManualShiftAssignmentCreation::class
    ];
    /*** @var Schedule $schedule */
    private $schedule;

    /**
     * Define the application's command schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        $this->schedule = $schedule;

        #$schedule->command('product-upload-csv')->dailyAt('00:00');
        $this->leaveAdjustmentCommand();
        $this->generatePayslipCommand();
        $this->announcementNotificationsCommand();
    }

    private function leaveAdjustmentCommand()
    {
        $this->schedule->command('sheba:leave-adjustment')->dailyAt('00:05');
    }

    private function generatePayslipCommand()
    {
        $this->schedule->command('sheba:generate-payslips')->dailyAt('00:20');
    }

    private function announcementNotificationsCommand()
    {
        $this->schedule->command('sheba:announcement-notifications')->everyMinute();
    }
}
