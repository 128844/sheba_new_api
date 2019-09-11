<?php namespace App\Http\Route\Prefix\V2\Partner\ID\Auth;

class IndexRoute
{
    public function set($api)
    {
        $api->group(['prefix' => '{partner}', 'middleware' => ['manager.auth']], function ($api) {
            $api->get('dashboard', 'Partner\DashboardController@get');
            $api->group(['prefix' => 'e-shop'], function ($api) {
                $api->group(['prefix' => 'order'], function ($api) {
                    $api->get('/', 'EShopOrderController@index');
                    $api->get('/{order}', 'EShopOrderController@show');
                });
            });
            $api->group(['prefix' => 'loans'], function ($api) {
                $api->post('/', 'SpLoanController@store');
                $api->get('/personal-info', 'SpLoanController@getPersonalInformation');
                $api->post('/personal-info', 'SpLoanController@updatePersonalInformation');

                $api->get('/business-info', 'SpLoanController@getBusinessInformation');
                $api->post('/business-info', 'SpLoanController@updateBusinessInformation');

                $api->get('/finance-info', 'SpLoanController@getFinanceInformation');
                $api->post('/finance-info', 'SpLoanController@updateFinanceInformation');

                $api->get('/nominee-info', 'SpLoanController@getNomineeInformation');

                $api->post('/nominee-grantor-info', 'SpLoanController@updateNomineeGrantorInformation');

                $api->get('/documents', 'SpLoanController@getDocuments');
                $api->post('/documents', 'SpLoanController@updateDocuments');

                $api->post('pictures', 'SpLoanController@updateProfilePictures');
                $api->post('bank-statement', 'SpLoanController@updateBankStatement');
                $api->post('trade-license', 'SpLoanController@updateTradeLicense');

                $api->get('/information-completion', 'SpLoanInformationCompletion@getLoanInformationCompletion');
                $api->get('/homepage', 'SpLoanController@getHomepage');
                $api->get('/bank-interest', 'SpLoanController@getBankInterest');
            });
            $api->group(['prefix' => 'pos'], function ($api) {
                $api->group(['prefix' => 'categories'], function ($api) {
                    $api->get('/', 'Pos\CategoryController@index');
                    $api->get('/master', 'Pos\CategoryController@getMasterCategoriesWithSubCategory');
                });
                $api->group(['prefix' => 'services'], function ($api) {
                    $api->get('/', 'Pos\ServiceController@index');
                    $api->post('/', 'Pos\ServiceController@store');
                    $api->group(['prefix' => '{service}'], function ($api) {
                        $api->get('/', 'Pos\ServiceController@show');
                        $api->post('/', 'Pos\ServiceController@update');
                        $api->delete('/', 'Pos\ServiceController@destroy');
                        $api->post('/toggle-publish-for-shop', 'Pos\ServiceController@togglePublishForShopStatus');
                    });
                });
                $api->group(['prefix' => 'orders'], function ($api) {
                    $api->get('/', 'Pos\OrderController@index');
                    $api->post('/', 'Pos\OrderController@store');
                    $api->post('/quick-store', 'Pos\OrderController@quickStore');
                    $api->group(['prefix' => '{order}'], function ($api) {
                        $api->get('/', 'Pos\OrderController@show');
                        $api->post('/', 'Pos\OrderController@update');
                        $api->post('/collect-payment', 'Pos\OrderController@collectPayment');
                        $api->get('/send-sms', 'Pos\OrderController@sendSms');
                        $api->get('/send-email', 'Pos\OrderController@sendEmail');
                        $api->get('/download-invoice', 'Pos\OrderController@downloadInvoice');
                        $api->post('store-note', 'Pos\OrderController@storeNote');
                    });
                });

                $api->group(['prefix' => 'customers'], function ($api) {
                    $api->group(['prefix' => '{customer}'], function ($api) {
                        $api->post('/', 'Pos\CustomerController@update');
                        $api->get('orders', 'Pos\CustomerController@orders');
                    });
                });

                $api->resources(['customers' => 'Pos\CustomerController']);
                $api->get('settings', 'Pos\SettingController@getSettings');
                $api->post('due-payment-request-sms', 'Pos\SettingController@duePaymentRequestSms');
            });
            $api->group(['prefix' => 'categories'], function ($api) {
                $api->get('/all', 'CategoryController@getPartnerLocationCategory');
                $api->get('/tree', 'PartnerController@getCategoriesTree');
                $api->get('/untagged', 'PartnerController@untaggedCategories');
                $api->get('/location/{location}', 'PartnerController@getLocationWiseCategory');
                $api->group(['prefix' => '{category}'], function ($api) {
                    $api->get('/', 'PartnerController@getSecondaryCategory');
                    $api->get('/all-services', 'PartnerController@getLocationWiseCategoryService');
                    $api->post('/update', 'PartnerController@updateSecondaryCategory');
                    $api->get('/services/{service}', 'PartnerController@serviceOption');
                    $api->post('/services/{service}', 'PartnerController@changePublicationStatus');
                });
            });
            $api->post('/bkash', 'PartnerController@storeBkashNumber');
            $api->get('services', 'Partner\PartnerServiceController@index');
            $api->group(['prefix' => 'services'], function ($api) {
                $api->get('/', 'Partner\PartnerServiceController@index');
                $api->post('/', 'Partner\PartnerServiceController@store');
                $api->put('{service}', 'Partner\PartnerServiceController@update');
            });
            $api->get('operations', 'Partner\OperationController@index');
            $api->post('operations', 'Partner\OperationController@store');
            $api->post('register', 'CustomerController@store');
            $api->post('categories', 'Partner\OperationController@saveCategories');
            $api->post('add-categories', 'CategoryController@addCategories');
            $api->post('vat-registration-number', 'PartnerController@addVatRegistrationNumber');
            $api->post('top-up', 'TopUpController@topUp');
            $api->get('search', 'SearchController@search');
            $api->group(['prefix' => 'subscriptions'], function ($api) {
                $api->get('/', 'Partner\PartnerSubscriptionController@index');
                $api->post('/', 'Partner\PartnerSubscriptionController@store');
                $api->post('/upgrade', 'Partner\PartnerSubscriptionController@update');
            });
            $api->group(['prefix' => 'customer-subscriptions'], function ($api) {
                $api->get('order-lists', 'Partner\CustomerSubscriptionController@index');
                $api->get('{subscription}/details', 'Partner\CustomerSubscriptionController@show');
                $api->post('{subscription}/bulk-accept ', 'Partner\CustomerSubscriptionController@bulkAccept');
            });
            $api->group(['prefix' => 'resources'], function ($api) {
                $api->post('/', 'Resource\PersonalInformationController@store');
                $api->group(['prefix' => '{resource}', 'middleware' => ['partner_resource.auth']], function ($api) {
                    $api->get('/', 'Resource\PersonalInformationController@index');
                    $api->post('/', 'Resource\PersonalInformationController@update');
                });
            });
            $api->get('bonus-history', 'Partner\PartnerBonusWalletController@transactions');
            $api->get('completion', 'Partner\ProfileCompletionController@getProfileCompletion');
            $api->get('collections', 'PartnerOrderPaymentController@index');
            $api->get('training', 'PartnerTrainingController@redirect');
            $api->post('pay-sheba', 'PartnerTransactionController@payToSheba');
            $api->group(['prefix' => 'orders'], function ($api) {
                $api->group(['prefix' => '{order}', 'middleware' => ['partner_order.auth']], function ($api) {
                    $api->get('/', 'PartnerOrderController@showV2');
                    $api->get('bills', 'PartnerOrderController@getBillsV2');
                    $api->post('services', 'PartnerOrderController@addService');
                    $api->post('collect', 'PartnerOrderController@collectMoney');
                    $api->get('retry-rider-search/{logistic_order_id}', 'PartnerOrderController@retryRiderSearch');
                });
            });
            $api->group(['prefix' => 'jobs'], function ($api) {
                $api->group(['prefix' => '{job}', 'middleware' => ['partner_job.auth']], function ($api) {
                    $api->put('/', 'PartnerJobController@update');

                    $api->group(['prefix' => 'materials'], function ($api) {
                        $api->get('/', 'PartnerJobController@getMaterials');
                    });
                    $api->group(['prefix' => 'cancel-requests'], function ($api) {
                        $api->post('/', 'PartnerCancelRequestController@store');
                        $api->get('reasons', 'PartnerCancelRequestController@cancelReasons');
                    });
                });
                $api->get('/cancel-request', 'PartnerJobController@cancelRequests');
            });
            $api->group(['prefix' => 'job_service/{job_service}'], function ($api) {
                $api->post('/update', 'JobServiceController@update');
                $api->delete('/', 'JobServiceController@destroy');
            });
            $api->group(['prefix' => 'complains'], function ($api) {
                $api->get('/', 'ComplainController@index');
                $api->post('/', 'ComplainController@storeForPartner');
                $api->get('/list', 'ComplainController@complainList');
                $api->get('/resolved-category', 'ComplainController@resolvedCategory');
                $api->group(['prefix' => '{complain}'], function ($api) {
                    $api->post('/', 'ComplainController@postPartnerComment');
                    $api->get('/', 'ComplainController@showPartnerComplain');
                    $api->post('/status', 'ComplainController@updateStatus');
                });
            });
            $api->group(['prefix' => 'rewards'], function ($api) {
                $api->get('/', 'Partner\PartnerRewardController@index');
                $api->get('/history', 'Partner\PartnerRewardController@history');
                $api->group(['prefix' => 'shop'], function ($api) {
                    $api->get('/', 'Partner\PartnerRewardShopController@index');
                    $api->get('/history', 'Partner\PartnerRewardShopController@history');
                    $api->post('/purchase', 'Partner\PartnerRewardShopController@purchase');
                    $api->get('/purchasable', 'Partner\PartnerRewardShopController@purchasable');
                });
                $api->get('/{reward}', 'Partner\PartnerRewardController@show');
            });
            $api->group(['prefix' => 'notifications'], function ($api) {
                $api->put('/', 'Partner\PartnerNotificationController@update');
            });
            $api->get('get-profile', 'ResourceController@getResourceData');
            $api->get('settings', 'Partner\OperationController@isOnPremiseAvailable');
            $api->get('my-customer-info', 'Partner\AsCustomerController@getResourceCustomerProfile');
            $api->group(['prefix' => 'partner-wallet'], function ($api) {
                $api->post('purchase', 'PartnerWalletController@purchase');
                $api->post('validate', 'PartnerWalletController@validatePayment');
            });
            $api->get('sales', 'Partner\SalesStatisticsController@index');
            $api->get('performance', 'Partner\PerformanceController@index');
            $api->group(['prefix' => 'campaigns'], function ($api) {
                $api->group(['prefix' => 'sms'], function ($api) {
                    $api->get('/settings', 'SmsCampaignOrderController@getSettings');
                    $api->post('/create', 'SmsCampaignOrderController@create');
                    $api->get('/templates', 'SmsCampaignOrderController@getTemplates');
                    $api->get('/history', 'SmsCampaignOrderController@getHistory');
                    $api->get('/history/{history_id}/details', 'SmsCampaignOrderController@getHistoryDetails');
                    $api->get('/faq', 'FaqController@getPartnerSmsCampaignFaq');
                    $api->get('/test-queue-run', 'SmsCampaignOrderController@processQueue');
                });
            });
            $api->get('served-customers', 'PartnerController@getServedCustomers');
            $api->post('change-leave-status', 'PartnerController@changeLeaveStatus');
            $api->post('change-logo', 'PartnerController@changeLogo');
            $api->group(['prefix' => 'vouchers'], function ($api) {
                $api->get('/dashboard', 'VoucherController@dashboard');
                $api->get('/', 'VoucherController@index');
                $api->get('/{voucher}', 'VoucherController@show');
                $api->post('/', 'VoucherController@store');
                $api->post('validity-check', 'VoucherController@validateVoucher');
                $api->group(['prefix' => '{voucher}'], function ($api) {
                });
            });
        });
    }
}