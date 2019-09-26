<?php namespace Sheba\Reports;

use Barryvdh\DomPDF\PDF;
use Illuminate\Support\Facades\File;
use Sheba\FileManagers\CdnFileManager;

class PdfHandler extends Handler
{
    /** @var PDF */
    use CdnFileManager;
    private $pdf;
    private $downloadFormat = "pdf";

    public function __construct()
    {
        $this->pdf = app('dompdf.wrapper');
    }

    public function create()
    {
        $this->pdf->loadView($this->viewFileName, $this->data);
        return $this;
    }

    public function download($options = null)
    {
        if ($options) {
            $this->pdf->setOptions(['defaultFont' => 'kalpurush']);
        }
        $this->create();
        return $this->pdf->stream("$this->filename.$this->downloadFormat");
    }

    public function save()
    {
        $this->create();
        if (!is_dir(public_path('temp'))) {
            mkdir(public_path('temp'), 0777, true);
        }
        $path = public_path('temp') . '/' . $this->filename . time() . '.' . $this->downloadFormat;
        $this->pdf->save($path);
        $cdn = $this->saveFileToCDN($path, 'invoices/pdf/', $this->filename . '.' . $this->downloadFormat);
        File::delete($path);
        return $cdn;

    }

    protected function getViewPath()
    {
        return "reports.pdfs.";
    }
}
