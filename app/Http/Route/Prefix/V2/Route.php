<?php

namespace App\Http\Route\Prefix\V2;

use App\Http\Route\Prefix\V2\Resource\ResourceRoute;
use App\Http\Route\Prefix\V2\Partner\PartnerRoute;
use Sheba\ShebaPay\Routes\PartnerRoutes as ShebaPayPartnerRoutes;

class Route
{
    public function set($api)
    {
        $api->group(['prefix' => 'v2', 'namespace' => 'App\Http\Controllers'], function ($api) {
            (new UserRoute())->set($api);
            (new CategoryRoute())->set($api);
            (new PaymentLinkRoute())->set($api);
            (new AccountingRoute())->set($api);
            (new CustomerRoute())->set($api);
            (new AffiliateRoute())->set($api);
            (new PartnerRoute())->set($api);
            (new HelpRoute())->set($api);
            (new ResourceRoute())->set($api);
            (new EmployeeRoute())->set($api);
            $api->post('training-status-update', 'ResourceController@trainingStatusUpdate');
            $api->post('profile-check', 'Profile\ProfileController@checkProfile');
            $api->post('newsletter', 'NewsletterController@create');
            $api->get('partner/dashboard-by-token', 'PartnerController@dashboardByToken');
            $api->group(['prefix' => 'profile'], function ($api) {
                $api->post('registration/partner', 'Auth\PartnerRegistrationController@registerByProfile')->middleware('jwtAuth');
                $api->post('registration/affiliate', 'Auth\AffiliateRegistrationController@registerByProfile')->middleware('jwtAuth');
                $api->post('change-picture', 'ProfileController@changePicture')->middleware('jwtAuth');
                $api->post('nid-submit', 'ProfileController@storeNid')->middleware('jwtAuth');
                $api->post('nid-submit-test', 'ProfileController@storeNidTest')->middleware('jwtAuth');
                $api->post('information', 'ProfileController@updateProfileInfo')->middleware('jwtAuth');
            });
            $api->get('validate-location', 'LocationController@validateLocation');
            $api->get('partners', 'PartnerLocationController@getPartners')->middleware('throttle:40');
            $api->get('lite-partners', 'PartnerLocationController@getLitePartners')->middleware('throttle:6');
            $api->post('subscription', 'PushSubscriptionController@store');
            $api->get('car-rental-info', 'ShebaController@sendCarRentalInfo');
            $api->get('payments', 'ShebaController@getPayments');
            $api->get('butcher-info', 'ShebaController@sendButcherInfo');
            $api->post('service-requests', 'ServiceRequestController@store');
            $api->get('validate-transaction-id', 'PartnerTransactionController@validateTransactionId');
            $api->post('transactions/{transactionID}', 'ShebaController@checkTransactionStatus');
            $api->get('transactions/info/{transactionID}', 'ShebaController@paymentInitiatedInfo');
            $api->get('transactions/{transactionID}', 'ShebaController@checkTransactionStatus');
            // $api->post('password/email', 'Auth\PasswordController@sendResetPasswordEmail');
            $api->post('password/validate', 'Auth\PasswordController@validatePasswordResetCode');
            // $api->post('password/reset', 'Auth\PasswordController@reset');
            $api->post('events', 'EventController@store');
            $api->get('top-up/fail/ssl', 'TopUpController@sslFail');
            $api->get('top-up/success/ssl', 'TopUpController@sslSuccess');
            $api->post('top-up/status-update', 'TopUpController@statusUpdate');
            $api->post('top-up/bdrecharge/status', 'TopUpController@bdRechargeStatusUpdate');
            $api->get('top-up/paystation/status', 'TopUpController@payStationStatusUpdate');
            $api->group(['prefix' => 'wallet'], function ($api) {
                $api->post('recharge', 'WalletController@recharge');
                $api->post('purchase', 'WalletController@purchase');
                $api->post('validate', 'WalletController@validatePayment');
                $api->get('faqs', 'WalletController@getFaqs');
                $api->get('gift-cards', 'GiftCardController@getGiftCards');
            });
            $api->group(['prefix' => 'faqs'], function ($api) {
                $api->get('order', 'JobController@getFaqs');
            });
            $api->group(['prefix' => 'ssl'], function ($api) {
                $api->post('validate', 'SslController@validatePayment');
            });
            $api->group(['prefix' => 'shurjopay'], function ($api) {
                $api->get('validate', 'Payment\ShurjoPayPGWController@validatePayment');
            });
            $api->group(['prefix' => 'bkash'], function ($api) {
                $api->post('validate', 'BkashController@validatePayment');
                $api->group(['prefix' => 'tokenized'], function ($api) {
                    $api->group(['prefix' => 'payment'], function ($api) {
                        $api->get('validate', 'Bkash\BkashTokenizedController@validatePayment');
                        $api->post('/', 'Bkash\BkashTokenizedController@tokenizePayment');
                    });
                    $api->group(['prefix' => 'agreement'], function ($api) {
                        $api->get('validate', 'Bkash\BkashTokenizedController@validateAgreement');
                    });
                });
                $api->get('paymentID/{paymentID}', 'BkashController@getPaymentInfo');
                $api->get('token/{paymentID}', 'BkashController@token');
            });
            $api->group(['prefix' => 'orders'], function ($api) {
                $api->get('online', 'OrderController@clearPayment');
                $api->group(['prefix' => 'payments'], function ($api) {
                    $api->post('success', 'SslController@validatePayment');
                    $api->post('fail', 'SslController@validatePayment');
                    $api->post('cancel', 'SslController@validatePayment');
                });
            });
            $api->group(['prefix' => 'payments/aamarpay'], function ($api) {
                $api->post('success', 'AamarpayController@validatePayment');
                $api->post('fail', 'AamarpayController@validatePayment');
                $api->post('cancel', 'AamarpayController@validatePayment');
            });
            $api->group(['prefix' => 'utility-orders'], function ($api) {
                $api->group(['prefix' => '{utility_order}'], function ($api) {
                    $api->post('bills/clear', 'UtilityController@clearBills');
                });
            });
            $api->group(['prefix' => 'payments'], function ($api) {
                $api->group(['prefix' => 'cbl'], function ($api) {
                    $api->post('success', 'CblController@validateCblPGR');
                    $api->post('fail', 'CblController@validateCblPGR');
                    $api->post('cancel', 'CblController@validateCblPGR');
                });
                $api->group(['prefix' => 'port-wallet'], function ($api) {
                    $api->post('ipn', 'PortWalletController@ipn');
                    $api->get('redirect-without-validate', 'PortWalletController@redirectWithoutValidation');
                    $api->get('validate-on-redirect', 'PortWalletController@validateOnRedirect');
                });
                $api->group(['prefix' => 'ok-wallet'], function ($api) {
                    $api->post('success', 'OkWalletController@validatePayment');
                    $api->post('fail', 'OkWalletController@validatePayment');
                    $api->post('cancel', 'OkWalletController@validatePayment');
                });
                $api->group(['prefix' => 'upay'], function ($api) {
                    $api->get('/', 'Payments\\UpayController@validatePayment');
                    $api->post('/', 'Payments\\UpayController@validatePayment');
                });
                $api->group(['prefix' => 'paystation'], function ($api) {
                    $api->get('/ipn', 'Payments\\PaystationController@validatePayment');
                    $api->post('/ipn', 'Payments\\PaystationController@validatePayment');
                });
            });
            $api->group(['prefix' => 'login'], function ($api) {
                $api->post('gmail', 'Auth\GoogleController@login');
            });
            $api->group(['prefix' => 'register'], function ($api) {
                $api->post('gmail', 'Auth\GoogleController@register');
                $api->post('kit/partner', 'Auth\PartnerRegistrationController@register');
                $api->post('partner-by-resource', 'Auth\PartnerRegistrationController@registerByResource');
            });
            $api->get('times', 'ScheduleTimeController@index');
            $api->get('settings', 'HomePageSettingController@index');
            $api->get('settings-new', 'HomePageSettingController@indexNew');
            $api->get('campaigns', 'CampaignController@index');
            $api->get('settings/top-up', 'TopUpController@getVendor');
            $api->get('settings/car', 'HomePageSettingController@getCar');
            $api->get('home-grids', 'HomeGridController@index');
            $api->group(['prefix' => 'category-groups'], function ($api) {
                $api->get('/', 'CategoryGroupController@index');
                $api->group(['prefix' => '{id}'], function ($api) {
                    $api->get('', 'CategoryGroupController@show');
                });
            });
            $api->group(['prefix' => 'service-groups'], function ($api) {
                $api->get('/', 'ServiceGroupController@index');
                $api->group(['prefix' => '{id}'], function ($api) {
                    $api->get('', 'ServiceGroupController@show');
                });
            });
            $api->group(['prefix' => 'offer-groups'], function ($api) {
                $api->get('/', 'OfferGroupController@index');
                $api->group(['prefix' => '{id}'], function ($api) {
                    $api->get('', 'OfferGroupController@show');
                });
            });
            $api->group(['middleware' => 'terminate'], function ($api) {
                (new BusinessRoute())->set($api);
            });
            $api->group(['prefix' => 'services'], function ($api) {
                $api->get('', 'ServiceController@index');
            });
            $api->group(['prefix' => 'subscriptions'], function ($api) {
                $api->get('/', 'SubscriptionController@index');
                $api->get('/partners', 'Subscription\CustomerSubscriptionController@getPartners');
                $api->get('/faq', 'FaqController@getSubscriptionFaq');
                $api->get('/all', 'SubscriptionController@all');
                $api->get('/{id}', 'SubscriptionController@show');
            });
            $api->group(['prefix' => 'locations'], function ($api) {
                $api->get('/', 'LocationController@index');
                $api->get('{location}/partners', 'PartnerController@findPartners');
                $api->get('current', 'LocationController@getCurrent');
            });
            $api->group(['prefix' => 'top-up', 'middleware' => ['topUp.auth']], function ($api) {
                $api->get('/vendor/{user?}', 'TopUp\TopUpController@getVendor')->where('user', "(business|partner|affiliate)");
                $api->post('/get-topup-token', 'TopUp\TopUpController@generateJwt');
                $api->get('/check-topup-token', 'TopUp\TopUpController@checkJwt');
                $api->post('/{user?}', 'TopUp\TopUpController@topUp')->where('user', "(business|partner|affiliate)");
                $api->post('/bulk', 'TopUp\TopUpController@bulkTopUp');
                $api->get('/history', 'TopUp\TopUpController@topUpHistory');
                $api->get('/active-bulk', 'TopUp\TopUpController@activeBulkTopUps');
                $api->get('/special-amount-data', 'TopUp\TopUpController@specialAmount');
                $api->get('bulk-list', 'TopUp\TopUpController@bulkList');
                $api->get('/all-top-up', 'TopUp\TopUpController@allTopUps');
                /**
                 * FOR TEST
                 * $api->post('top-up-test', 'TopUp\\TopUpController@topUpTest');
                 */
            });
            $api->group(['prefix' => 'resources/{resource}', 'middleware' => ['resource.auth']], function ($api) {
                $api->group(['prefix' => 'jobs'], function ($api) {
                    $api->group(['prefix' => '{job}', 'middleware' => ['resource_job.auth']], function ($api) {
                        $api->get('bills', 'ResourceJobController@getBills');
                        $api->post('extends', 'ResourceScheduleController@extendTime');
                        $api->post('reviews', 'ResourceJobRateController@store');
                        $api->group(['prefix' => 'rates'], function ($api) {
                            $api->get('/', 'ResourceJobRateController@index');
                            $api->post('/', 'RateController@storeCustomerReview');
                        });
                    });
                });
            });
            $api->get('updates', 'UpdateController@getUpdates');
            $api->get('ek-sheba/authenticate', 'EkshebaController@authenticate');
            /**
             * PROFILE EXISTENCE CHECK. PUBLIC API
             */
            // $api->get('get-profile-info', 'ProfileController@getProfile')->middleware('sheba_network');
            // $api->get('get-profile-info-by-mobile', 'ProfileController@getProfileInfoByMobile');
            // $api->post('profile/{id}/update-profile-document', 'ProfileController@updateProfileDocument')->middleware('profile.auth');
            // $api->post('profile-update/by/{id}', 'ProfileController@update')->middleware('profile.auth');
            // $api->get('{id}/get-jwt', 'ProfileController@getJWT')->middleware('profile.auth');
            // $api->get('{id}/refresh-token', 'ProfileController@refresh');
            $api->post('admin/payout', 'Bkash\\BkashPayoutController@pay');
            $api->post('admin/payout-balance', 'Bkash\\BkashPayoutController@queryPayoutBalance');
            $api->post('admin/bkash-balance', 'Bkash\\BkashPayoutController@queryBalance');
            // $api->post('forget-password', 'ProfileController@forgetPassword');
            /**
             * EMI INFO
             */
            $api->group(['prefix' => 'emi-info'], function ($api) {
                $api->get('/', 'ShebaController@getEmiInfo');
                $api->get('/manager', 'ShebaController@emiInfoForManager');
                $api->get('/bank-list', 'ShebaController@getEmiBankList');
            });
            $api->group(['prefix' => 'tickets', 'middleware' => 'jwtGlobalAuth'], function ($api) {
                // $api->get('validate-token', 'ProfileController@validateJWT');
                $api->get('payments', 'ShebaController@getPayments');
                (new TransportRoute())->set($api);
                (new MovieTicketRoute())->set($api);
            });
            // $api->get('refresh-token', 'ProfileController@refresh');
            $api->get('service-price-calculate', 'Service\ServicePricingController@getCalculatedPrice');
            $api->post('due-tracker/create-pos-order-payment', 'Pos\OrderController@createPayment');
            $api->delete('due-tracker/remove-pos-order-payment/{pos_order_id}', 'Pos\OrderController@removePayment');
            $api->group(['prefix' => 'voucher'], function ($api) {
                $api->post('/vendor', 'VoucherController@voucherAgainstVendor');
            });
        });
        (new ShebaPayPartnerRoutes())->set($api);
        return $api;
    }
}
