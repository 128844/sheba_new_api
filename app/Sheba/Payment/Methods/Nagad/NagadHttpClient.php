<?php


namespace Sheba\Payment\Methods\Nagad;


use Sheba\TPProxy\TPRequest;

class NagadHttpClient
{
    public function call(TPRequest $request)
    {
        $url    = curl_init($request->getUrl());
        $inputs = $request->getInput();
        if (is_array($inputs)) $inputs = json_encode($inputs);
        curl_setopt($url, CURLOPT_HTTPHEADER, $request->getHeaders());
        curl_setopt($url, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($url, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($url, CURLOPT_POSTFIELDS, $inputs);
        curl_setopt($url, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($url, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($url, CURLOPT_SSL_VERIFYPEER, 0);

        $resultData  = curl_exec($url);
        $ResultArray = json_decode($resultData, true);
        curl_close($url);
        return $ResultArray;
    }

}
