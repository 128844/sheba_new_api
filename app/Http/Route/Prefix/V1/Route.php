<?php namespace App\Http\Route\Prefix\V1;

use App\Http\Route\Prefix\V1\Partner\PartnerRoute;
use App\Http\Route\Prefix\V1\Resource\ResourceRoute;
use Sheba\Dal\SmsCampaignOrder\SmsCampaignOrderRepository;

class Route
{
    public function set($api)
    {
        $api->get('test', function (SmsCampaignOrderRepository $orderRepository){
            $order = $orderRepository->create([
                'title' => 'title',
                'message' => 'message',
                'partner_id' => 216648,
                'rate_per_sms' => .01,
                'bulk_id' => null
            ]);
            return $order->id;
        });
        $api->group(['prefix' => 'v1', 'namespace' => 'App\Http\Controllers'], function ($api) {
            $api->get('hour-logs', 'ShebaController@getHourLogs');
            $api->group(['middleware' => 'terminate'], function ($api) {
                (new EmployeeRoute())->set($api);
            });
            (new PartnerRoute())->set($api);
            $api->post('login/apple', 'Auth\AppleController@login');
            $api->post('register/apple', 'Auth\AppleController@register');
            $api->group(['prefix' => 'geo', 'middleware' => 'geo.auth'], function ($api) {
                $api->get('geocode/reverse', 'GeocodeController@reverseGeocode');
            });
            $api->group(['prefix' => 'vendors', 'middleware' => ['vendor.auth']], function ($api) {
                $api->get('times', 'Vendor\ShebaController@getTimes');
                $api->get('categories', 'Vendor\CategoryController@index');
                $api->get('categories/{category}/secondaries', 'Vendor\CategoryController@get');
                $api->get('categories/{category}/services', 'Vendor\CategoryController@getServices');
                $api->get('partners', 'Vendor\PartnerController@getPartners');
                $api->get('orders/{order}', 'Vendor\OrderController@show');
                $api->get('orders/{order}/bills', 'Vendor\OrderController@getBills');
                $api->post('orders', 'Vendor\OrderController@placeOrder');
                $api->get('locations', 'Vendor\LocationController@index');
                $api->group(['prefix' => 'topup'], function ($api) {
                    $api->post('/', 'Vendor\TopUpController@topUp');
                    $api->get('/', 'Vendor\TopUpController@history');
                    $api->get('{topup}', 'Vendor\TopUpController@historyDetails');
                });
                $api->get('balance', 'Vendor\ShebaController@getDetails');
            });
            $api->get('categories', ['uses' => 'CategoryController@index']);
            $api->get('categories/{category}/secondaries', ['uses' => 'CategoryController@get']);
            $api->get('categories/{category}/services', ['uses' => 'CategoryController@getServices']);
            $api->post('register', 'Auth\RegistrationController@register');
            $api->group(['prefix' => 'login'], function ($api) {
                $api->post('facebook', 'FacebookController@login');
            });
            $api->group(['prefix' => 'register'], function ($api) {
                $api->post('email', 'Auth\RegistrationController@registerByEmailAndMobile');
                $api->post('facebook', 'FacebookController@register');
            });
            $api->post('continue-with-kit', 'FacebookController@continueWithKit');
            $api->post('continue-with-facebook', 'FacebookController@continueWithFacebook');
            $api->get('authenticate', 'AccountController@checkForAuthentication');
            $api->post('account', 'AccountController@encryptData');
            $api->get('decrypt', 'AccountController@decryptData');
            $api->get('versions', 'ShebaController@getVersions');
            $api->get('images', 'ShebaController@getImages');
            $api->get('sliders', 'SliderController@index');
            $api->get('locations', 'LocationController@getAllLocations');
            $api->get('divisions-with-districts', 'LocationController@getDivisionsWithDistrictsAndThana');
            $api->get('districts-with-thanas', 'LocationController@getDistrictsWithThanas');
            $api->get('lead-reward', 'ShebaController@getLeadRewardAmount');
            $api->get('search', 'SearchController@searchService');
            $api->get('career', 'CareerController@getVacantPosts');
            $api->post('career', 'CareerController@apply');
            $api->get('category-service', 'CategoryServiceController@getCategoryServices');
            $api->get('job-times', 'JobController@getPreferredTimes');
            $api->get('times', 'JobController@getPreferredTimes');
            $api->get('cancel-job-reasons', 'JobController@cancelJobReasons');
            $api->post('voucher-valid', 'CheckoutController@validateVoucher');
            $api->post('vouchers', 'CheckoutController@validateVoucher');
            $api->post('rating', 'ReviewController@giveRatingFromEmail');
            $api->post('sms', 'SmsController@send')->middleware('throttle:2,60');
            $api->post('faq', 'ShebaController@sendFaq');
            $api->get('lpg-service', 'ServiceController@getLpg');
            $api->group(['prefix' => 'offers'], function ($api) {
                $api->get('/', 'OfferController@index');
                $api->get('/partner-offer', 'OfferController@getPartnerOffer');
                $api->get('{offer}', 'OfferController@show');
            });
            $api->group(['prefix' => 'blogs'], function ($api) {
                $api->get('/', 'BlogController@index')->name('blogs.get');
            });
            $api->group(['prefix' => 'feedback', 'middleware' => ['manager.auth']], function ($api) {
                $api->post('/', 'FeedbackController@create');
            });
            $api->get('offer/{offer}/similar', 'ShebaController@getSimilarOffer');
            $api->group(['prefix' => 'navigation'], function ($api) {
                $api->get('/', 'NavigationController@getNavList');
            });
            $api->group(['prefix' => 'jobs'], function ($api) {
                $api->get('times', 'JobController@getPreferredTimes');
            });
            $api->group(['prefix' => 'categories'], function ($api) {
                $api->get('/', 'CategoryController@index');
                $api->get('{category}/secondaries', 'CategoryController@getSecondaries');
                $api->get('{category}/secondaries/services', 'CategoryController@getSecondaryServices');
                $api->get('{category}/services', 'CategoryController@getServices');
                $api->get('{category}/master', 'CategoryController@getMaster');
            });
            $api->group(['prefix' => 'service'], function ($api) {
                $api->get('{service}/get-prices', 'ServiceController@getPrices');
                $api->get('{service}/location/{location}/partners', 'ServiceController@getPartners');
                $api->post('{service}/location/{location}/partners', 'ServiceController@getPartners');
                $api->post('{service}/{location}/change-partner', 'ServiceController@changePartner');
                $api->get('/{service}/reviews', 'ServiceController@getReviews');
                //For Back-end
                $api->post('{service}/change-partner', 'ServiceController@changePartnerWithoutLocation');
            });
            $api->group(['prefix' => 'services'], function ($api) {
                $api->get('/', 'ServiceController@index');
                $api->get('{service}', 'ServiceController@get');
                $api->get('{service}/valid', 'ServiceController@checkForValidity');
                $api->get('{service}/similar', 'ServiceController@getSimilarServices');
                $api->get('{service}/reviews', 'ServiceController@getReviews');
                $api->get('{service}/locations/{location}/partners', 'ServiceController@getPartnersOfLocation');
                $api->post('{service}/locations/{location}/partners', 'ServiceController@getPartners');
            });
            $api->group(['prefix' => 'partner'], function ($api) {
                $api->get('/', 'PartnerController@index');
                $api->get('{partner}/services', 'PartnerController@getPartnerServices');
                $api->get('{partner}/reviews', 'PartnerController@getReviews');
            });
            $api->group(['prefix' => 'customer', 'middleware' => ['customer.auth']], function ($api) {
                $api->get('{customer}', 'CustomerController@getCustomerInfo');
                $api->delete('{customer}','CustomerController@delete');
                $api->post('{customer}/edit', 'CustomerController@editInfo');
                $api->get('{customer}/general-info', 'CustomerController@getCustomerGeneralInfo');
                $api->get('{customer}/intercom-info', 'CustomerController@getIntercomInfo');
                $api->get('{customer}/checkout-info', 'CustomerController@getDeliveryInfo');
                $api->get('{customer}/order-list', 'OrderController@getNotClosedOrderInfo');
                $api->get('{customer}/order-history', 'OrderController@getClosedOrderInfo');
                $api->get('{customer}/cancel-order-list', 'OrderController@getCancelledOrders');
                $api->get('{customer}/referral', 'CustomerController@getReferral');
                $api->post('{customer}/send-referral-request-email', 'CustomerController@sendReferralRequestEmail');
                $api->get('{customer}/promo', 'PromotionController@getPromo');
                $api->post('{customer}/promo', 'PromotionController@addPromo');
                $api->post('{customer}/suggest-promo', 'PromotionController@suggestPromo');

                $api->post('{customer}/sp-payment', 'CheckoutController@spPayment');
                $api->post('{customer}/order-valid', 'OrderController@checkOrderValidity');
                $api->post('{customer}/modify-review', 'ReviewController@modifyReview');
                $api->get('{customer}/job/{job}', 'JobController@getInfo');
                $api->post('{customer}/{job}/cancel', 'JobController@cancelJob');

                $api->post('{customer}/ask-quotation', 'CustomOrderController@askForQuotation');
                $api->get('{customer}/custom-order', 'CustomOrderController@getCustomOrders');
                $api->get('{customer}/custom-order/{custom_order}/quotation', 'CustomOrderController@getCustomOrderQuotation');
                $api->get('{customer}/custom-order/{custom_order}/discussion', 'CustomOrderController@getCommentForDiscussion');
                $api->post('{customer}/custom-order/{custom_order}/discussion', 'CustomOrderController@postCommentOnDiscussion');

//            $api->post('{customer}/checkout/place-order', 'CheckoutController@placeOrder');
//            $api->post('{customer}/checkout/place-order-with-online-payment', 'CheckoutController@placeOrderWithPayment');
            });
            $api->group(['prefix' => 'customers/{customer}', 'middleware' => ['customer.auth']], function ($api) {
                $api->get('/', 'CustomerController@index');
                $api->group(['prefix' => 'edit'], function ($api) {
                    $api->put('/', 'CustomerController@update');
                    $api->put('email', 'CustomerController@updateEmail');
                    //$api->put('password', 'CustomerController@updatePassword');
                    $api->post('picture', 'CustomerController@updatePicture');
                    $api->put('mobile', 'CustomerController@updateMobile');
                });
                $api->post('reviews', 'ReviewController@modifyReview');
                $api->get('notifications', 'CustomerController@getNotifications');
                $api->post('suggest-promo', 'PromotionController@suggestPromo');
                $api->put('addresses/{address}', 'CustomerAddressController@update');
            });
            $api->group(['prefix' => 'checkout'], function ($api) {
                $api->get('place-order-final', 'CheckoutController@placeOrderFinal');
                $api->get('sp-payment-final', 'CheckoutController@spPaymentFinal');
            });
            $api->group(['prefix' => 'business'], function ($api) {
                $api->get('check-url', 'BusinessController@checkURL');
                $api->get('type-category', 'BusinessController@getTypeAndCategories');

                $api->group(['prefix' => 'member', 'middleware' => ['member.auth']], function ($api) {
                    $api->get('/{member}/get-info', 'MemberController@getInfo');
                    $api->get('/{member}/get-profile-info', 'MemberController@getProfileInfo');
                    $api->post('/{member}/update-personal-info', 'MemberController@updatePersonalInfo');
                    $api->post('/{member}/update-professional-info', 'MemberController@updateProfessionalInfo');
                    $api->post('/{member}/change-NID', 'MemberController@changeNID');

                    $api->post('/{member}/create-business', 'BusinessController@create');
                    $api->post('{member}/check-business', 'BusinessController@checkBusiness');
                    $api->get('/{member}/show', 'BusinessController@show');

                    $api->get('{member}/business/{business}', 'BusinessController@getBusiness');
                    $api->post('{member}/business/{business}/update', 'BusinessController@update');
                    $api->post('{member}/business/{business}/change-logo', 'BusinessController@changeLogo');
                    $api->get('{member}/business/{business}/members', 'BusinessController@getMembers');
                    $api->get('{member}/business/{business}/requests', 'BusinessController@getRequests');
                    $api->post('{member}/business/{business}/manage-invitation', 'BusinessController@manageInvitation');
                    $api->get('{member}/business/{business}/get-member', 'BusinessMemberController@getMember');
                    $api->post('{member}/business/{business}/change-member-type', 'BusinessMemberController@changeMemberType');


                    $api->post('{member}/search', 'SearchController@searchBusinessOrMember');
                    $api->get('{member}/requests', 'MemberController@getRequests');

                    $api->post('{member}/send-invitation', 'InvitationController@sendInvitation');
                    $api->post('{member}/manage-invitation', 'MemberController@manageInvitation');
                });
            });
            $api->group(['prefix' => 'partners/{partner}', 'middleware' => ['manager.auth']], function ($api) {
                $api->get('dashboard', 'PartnerController@getDashboardInfo');
                $api->get('earnings', 'PartnerController@getEarnings');
                $api->get('reviews', 'PartnerController@getReviewInfo');
                $api->get('info', 'PartnerController@getInfo');
                $api->get('notifications', 'PartnerController@getNotifications');
                $api->get('notifications/{notification}', 'PartnerController@getNotification');

                $api->group(['prefix' => 'withdrawals'], function ($api) {
                    $api->get('/', 'PartnerWithdrawalRequestController@index');
                    $api->post('/', 'PartnerWithdrawalRequestController@store');
                    $api->put('{withdrawals}', 'PartnerWithdrawalRequestController@update');
                    $api->get('status', 'PartnerWithdrawalRequestController@getStatus');
                    $api->get('{withdrawals}/cancel', 'PartnerWithdrawalRequestController@cancel');
                });
                $api->group(['prefix' => 'transactions'], function ($api) {
                    $api->get('/', 'PartnerTransactionController@index');
                });
                $api->group(['prefix' => 'graphs'], function ($api) {
                    $api->get('orders', 'GraphController@getOrdersGraph');
                    $api->get('sales', 'GraphController@getSalesGraph');
                });
                $api->group(['prefix' => 'resources'], function ($api) {
                    $api->get('/', 'PartnerController@getResources');
                    $api->group(['prefix' => '{resource}', 'middleware' => ['partner_resource.auth']], function ($api) {
                        $api->get('/', 'ResourceController@show');
                        $api->get('reviews', 'ResourceController@getReviews');
                    });
                });
                $api->group(['prefix' => 'jobs'], function ($api) {
                    $api->get('/', 'PartnerJobController@index');

                    $api->group(['prefix' => '{job}', 'middleware' => ['partner_job.auth']], function ($api) {
                        $api->post('accept', 'PartnerJobController@acceptJobAndAssignResource');
                        $api->post('reject', 'PartnerJobController@declineJob');
                        $api->put('/', 'PartnerJobController@update');

                        $api->group(['prefix' => 'materials'], function ($api) {
                            $api->get('/', 'PartnerJobController@getMaterials');
                            $api->post('/', 'PartnerJobController@addMaterial')->middleware('concurrent_request:partner,update');
                            $api->put('/', 'PartnerJobController@updateMaterial')->middleware('concurrent_request:partner,update');
                        });
                    });
                });
                $api->group(['prefix' => 'orders'], function ($api) {
                    $api->get('new', 'PartnerOrderController@newOrders');
                    $api->get('/', 'PartnerOrderController@getOrders');

                    $api->group(['prefix' => '{order}', 'middleware' => ['partner_order.auth']], function ($api) {
                        $api->get('/', 'PartnerOrderController@show');
                        $api->get('bills', 'PartnerOrderController@getBillsV1');
                        $api->get('logs', 'PartnerOrderController@getLogs');
                        $api->get('payments', 'PartnerOrderController@getPayments');
                        $api->post('comments', 'PartnerOrderController@postComment');
                    });
                });
            });
            $api->group(['prefix' => 'resources/{resource}', 'middleware' => ['resource.auth']], function ($api) {
                $api->group(['prefix' => 'jobs'], function ($api) {
                    $api->get('/', 'ResourceJobController@index');
                    $api->group(['prefix' => '{job}', 'middleware' => ['resource_job.auth']], function ($api) {
                        $api->get('/', 'ResourceJobController@show');
                        $api->put('/', 'ResourceJobController@update');
                        $api->get('others', 'ResourceJobController@otherJobs');
                        $api->post('payment', 'ResourceJobController@collect');
                    });
                });
                $api->group(['prefix' => 'partners/{partner}', 'middleware' => ['manager.auth']], function ($api) {
                    $api->get('/', 'ResourceJobController@index');
                });
            });
            $api->group(['prefix' => 'affiliate/{affiliate}', 'middleware' => ['affiliate.auth']], function ($api) {
                $api->post('edit', 'AffiliateController@edit');
                $api->post('update-profile-picture', 'AffiliateController@updateProfilePic');
                $api->get('lead-info', 'AffiliateController@leadInfo');

                $api->get('wallet', 'AffiliateController@getWallet');
                $api->get('status', 'AffiliateController@getStatus');
                $api->get('affiliations', 'AffiliationController@index');
                $api->post('affiliations', 'AffiliationController@create');
                $api->post('partner-affiliates', 'PartnerAffiliationController@store');
                $api->get('partner-affiliates', 'PartnerAffiliationController@index');
            });
            $api->group(['prefix' => 'affiliates/{affiliate}', 'middleware' => ['affiliate.auth']], function ($api) {
                $api->post('edit', 'AffiliateController@edit');
                $api->get('leads', 'AffiliateController@leadInfo');
                $api->get('notifications', 'AffiliateController@getNotifications');

                $api->get('wallet', 'AffiliateController@getWallet');
                $api->get('status', 'AffiliateController@getStatus');
                $api->get('dashboard', 'AffiliateController@getDashboardInfo');
                $api->get('affiliations', 'AffiliationController@newIndex');
                $api->post('affiliations', 'AffiliationController@create');
                $api->get('transactions', 'AffiliateController@getTransactions');

                $api->get('leaderboard', 'AffiliateController@getLeaderboard');
                $api->group(['prefix' => 'ambassador'], function ($api) {
                    $api->get('/', 'AffiliateController@getGodFather');
                    $api->get('code', 'AffiliateController@getAmbassador');
                    $api->post('code', 'AffiliateController@joinClan');
                    $api->get('agents', 'AffiliateController@getAgents');
                    $api->get('summary', 'AffiliateController@getAmbassadorSummary');
                });
            });
            $api->group(['prefix' => 'profile', 'middleware' => ['profile.auth']], function ($api) {
                $api->post('change-picture', 'ProfileController@changePicture');
            });
            $api->group(['prefix' => 'bank-user', 'middleware' => 'jwtGlobalAuth'], function ($api) {
                $api->get('/notifications', 'BankUser\NotificationController@index');
                $api->get('/notification-seen/{id}', 'BankUser\NotificationController@notificationSeen');
            });
            $api->group(['prefix' => 'nagad'], function ($api) {
                $api->get('validate', 'NagadController@validatePayment');
            });
            $api->group(['prefix' => 'ebl'], function ($api) {
                $api->post('validate', 'EblController@validatePayment');
                $api->post('cancel', 'EblController@cancelPayment');
            });
            $api->get('profiles', 'Profile\ProfileController@getDetail')->middleware('jwtGlobalAuth');

            $api->post('register-mobile', 'ShebaController@registerCustomer');

            $api->group(['prefix'=>'ekyc', 'middleware' => 'jwtGlobalAuth'], function ($api) {
                $api->post('nid-ocr-data', 'EKYC\NidOcrController@storeNidOcrData');
                $api->post('face-verification', 'EKYC\FaceVerificationController@faceVerification');
                $api->get('get-liveliness-credentials', 'EKYC\FaceVerificationController@getLivelinessCredentials');
                $api->get('get-user-data', 'EKYC\FaceVerificationController@getUserNidData');
            });
            $api->group(['prefix'=>'ekyc', 'middleware' => 'shebaServer'], function ($api) {
                $api->get('resubmit-nid/{id}', 'EKYC\FaceVerificationController@resubmitToPorichoy');
            });
        });
        return $api;
    }
}
