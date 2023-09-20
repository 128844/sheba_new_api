<?php
use Illuminate\Support\Facades\Route;
$nameSpace = 'App\\Http\\Controllers';

Route::post('/v5/register/partner-by-sheba-pay',"Auth\\PartnerRegistrationController@registerShebaPay")->middleware('sheba_pay.basic-auth');