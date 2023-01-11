<?php

namespace App\Http\Route\Prefix\V3;

class BusinessRoute
{
    public function set($api)
    {
        $api->get('test-mail', 'B2b\TestMailsController@testMail');
        $api->post('business/register', 'B2b\RegistrationController@registerV3')->middleware('jwtAuth');
        $api->post('business/email-verify', 'Profile\ProfileController@verifyEmailWithVerificationCode')->middleware('jwtAuth');
        $api->get('business/send-verification-code', 'Profile\ProfileController@sendEmailVerificationCode')->middleware('jwtAuth');
        $api->get('business/send-verification-link', 'Profile\ProfileController@sendEmailVerificationlink')->middleware('jwtAuth');
        $api->post('business/token-verify', 'B2b\BusinessesController@tokenVerify');
        $api->group(['prefix' => 'businesses', 'middleware' => ['business.auth']], function ($api) {
            $api->group(['prefix' => '{business}'], function ($api) {
                $api->get('/topup-portal', 'B2b\BusinessesController@getTopUpPortalToken');
                $api->get('vendors', 'B2b\BusinessesController@getVendorsListV3');
                $api->group(['prefix' => 'approval-settings'], function ($api) {
                    $api->get('/', 'B2b\ApprovalSettingsController@index');
                    $api->get('/default', 'B2b\ApprovalSettingsController@showDefault');
                    $api->get('/modules', 'B2b\ApprovalSettingsController@getModules');
                    $api->post('/', 'B2b\ApprovalSettingsController@store');
                    $api->group(['prefix' => '{setting}'], function ($api) {
                        $api->get('/', 'B2b\ApprovalSettingsController@show');
                        $api->post('/', 'B2b\ApprovalSettingsController@update');
                        $api->delete('/', 'B2b\ApprovalSettingsController@delete');
                    });
                });
                $api->group(['prefix' => 'office-setting'], function ($api) {
                    $api->get('operational', 'B2b\AttendanceController@getOperationalOfficeSettings');
                    $api->post('operational', 'B2b\AttendanceController@updateOperationalOfficeSettings');
                    $api->get('attendance', 'B2b\AttendanceController@getAttendanceOfficeSettings');
                    $api->post('attendance', 'B2b\AttendanceController@updateAttendanceOfficeSettings');
                    $api->get('grace-policy', 'B2b\AttendanceController@getGracePolicy');
                    $api->get('unpaid-leave-policy', 'B2b\AttendanceController@getUnpaidLeavePolicy');
                    $api->post('unpaid-leave-policy', 'B2b\AttendanceController@createUnpaidLeavePolicy');
                    $api->get('checkin-checkout-policy', 'B2b\AttendanceController@getLateCheckinEarlyCheckoutPolicy');
                    $api->get('changes-logs', 'B2b\AttendanceController@getOfficeSettingChangesLogs');
                    $api->get('office-locations', 'B2b\AttendanceController@getOfficeLocations');
                });
                $api->group(['prefix' => 'leaves'], function ($api) {
                    $api->group(['prefix' => 'prorate'], function ($api) {
                        $api->get('/', 'B2b\ProrateController@indexV2');
                        $api->group(['prefix' => '{prorate}'], function ($api) {
                            $api->get('/employee-info', 'B2b\ProrateController@employeeInfo');
                            $api->get('/employee-prorate-logs', 'B2b\ProrateController@employeeLeaveProrateLog');
                        });
                    });
                });
                $api->group(['prefix' => 'announcements'], function ($api) {
                    $api->get('/', 'B2b\AnnouncementV2Controller@index');
                    $api->post('/', 'B2b\AnnouncementV2Controller@store');
                    $api->group(['prefix' => '{announcement}'], function ($api) {
                        $api->post('/', 'B2b\AnnouncementV2Controller@update');
                        $api->get('/', 'B2b\AnnouncementV2Controller@show');
                        $api->get('notification', 'B2b\AnnouncementV2Controller@notificationCount');
                    });
                });
            });
        });
    }
}
