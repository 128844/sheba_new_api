<?php namespace App\Repositories;

use App\Models\SmsTemplate;
use Exception;
use Sheba\Sms\Sms;

class SmsHandler
{
    private $template;
    private $sms; /** @var Sms */

    public function __construct($event_name)
    {
        $this->template = SmsTemplate::where('event_name', $event_name)->first();
        $this->sms = new Sms(); //app(Sms::class);
    }

    /**
     * @param $mobile
     * @param $variables
     * @return Sms
     * @throws Exception
     */
    public function send($mobile, $variables)
    {
        if (!$this->template->is_on) return $this->sms;

        $this->checkVariables($variables);

        $message = $this->template->template;
        foreach ($variables as $variable => $value) {
            $message = str_replace("{{" . $variable . "}}", $value, $message);
        }
        $sms = $this->sms->to($mobile)->msg($message);
        $sms->shoot();

        return $sms;
    }

    private function checkVariables($variables)
    {
        if (count(array_diff(explode(';', $this->template->variables), array_keys($variables)))){
            throw new Exception("Variable doesn't match");
        }
    }
}