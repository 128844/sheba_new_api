<?php namespace Sheba\TopUp\Jobs;

use App\Models\TopUpOrder;
use Excel;
use Exception;
use Maatwebsite\Excel\Readers\LaravelExcelReader;

use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;

use Sheba\Sms\Sms;

use Sheba\TopUp\TopUpExcel;

class TopUpExcelJob extends TopUpJob
{
    use FileManager, CdnFileManager;

    private $file;
    private $row;
    private $totalRow;
    /** @var Sms */
    private $sms;
    /** @var LaravelExcelReader */
    private $excel = null;

    public function __construct($agent, $vendor, TopUpOrder $topup_order, $file, $row, $total_row)
    {
        parent::__construct($agent, $vendor, $topup_order);

        $this->file = $file;
        $this->row = $row;
        $this->totalRow = $total_row;
        $this->sms = new Sms(); //app(Sms::class);
    }

    /**
     * @throws Exception
     */
    protected function takeUnsuccessfulAction()
    {
        $this->updateExcel('Failed', $this->topUp->getError()->errorMessage);
        $this->takeCompletedAction();
    }

    /**
     * @throws Exception
     */
    protected function takeSuccessfulAction()
    {
        $this->updateExcel('Successful');
        $this->takeCompletedAction();
    }

    private function getExcel()
    {
        if (!$this->excel) $this->excel = Excel::selectSheets(TopUpExcel::SHEET)->load($this->file);
        return $this->excel;
    }

    private function updateExcel($status, $message = null)
    {
        $this->getExcel()->getActiveSheet()->setCellValue(TopUpExcel::STATUS_COLUMN . $this->row, $status);
        if ($message) {
            $this->getExcel()->getActiveSheet()->setCellValue(TopUpExcel::MESSAGE_COLUMN . $this->row, $message);
        }
        $this->excel->save();
    }

    private function takeCompletedAction()
    {
        if ($this->row == $this->totalRow + 1) {
            $name = strtolower(class_basename($this->agent)) . '_' . dechex($this->agent->id);
            $file_name = $this->uniqueFileName($this->file, $name, $this->getExcel()->ext);
            $file_path = $this->saveFileToCDN($this->file, getBulkTopUpFolder(), $file_name);

            unlink($this->file);

            $msg = "Your top up request has been processed. You can find the results here: " . $file_path;
            $this->sms->shoot($this->agent->getMobile(), $msg);
        }
    }
}