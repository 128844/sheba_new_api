<?php namespace App\Http\Route\Prefix\V2;

class EmployeeRoute
{
    public function set($api)
    {
        $api->group(['prefix' => 'employee', 'middleware' => ['employee.auth']], function ($api) {
            $api->group(['prefix' => 'employee-visit'], function ($api) {
                $api->get('own-visit-history', 'Employee\VisitController@ownVisitHistoryV2');
                $api->get('team-visits', 'Employee\VisitController@teamVisitsListV2');
            });
            $api->group(['prefix' => 'profile'], function ($api) {
                $api->get('financial', 'Employee\ProfileController@getFinancialInfo');
                $api->get('official', 'Employee\ProfileController@getOfficialInfo');
                $api->post('official', 'Employee\ProfileController@updateOfficialInfo');
                $api->post('update', 'Employee\ProfileController@updateEmployee');
                $api->post('emergency', 'Employee\ProfileController@updateEmergencyInfo');
                $api->get('emergency', 'Employee\ProfileController@getEmergencyContactInfo');
                $api->get('personal', 'Employee\ProfileController@getPersonalInfo');
                $api->post('personal', 'Employee\ProfileController@updatePersonalInfo');
            });
        });
    }
}
