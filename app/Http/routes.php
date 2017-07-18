<?php

use App\Models\Group;
use App\Models\Navigation;

Route::get('/', function () {
//    $navigation = new Group();
//    $navigation->name = 'Work Station';
//    $navigation->navigation_id = '596c9de68543bc2dd4005c03';
//    $navigation->services = array(
//        array(
//            'id' => 127,
//            'name' => 'adad'
//        ),
//        array(
//            'id' => 1,
//            'name' => 'khela hobe'
//        )
//    );
//    $navigation->save();

    $nav = Navigation::where('publication_status', 1)->first();
    dd($nav->groups);

    return ['code' => 200, 'msg' => 'Success. This project will hold the api\'s'];
});

Route::get('email-verification/{customer}/{code}', 'CustomerController@emailVerification');
//Route::get('reset-password/{customer}/{code}', 'PasswordController@getResetPasswordForm');
//Route::post('reset-password/{customer}/{code}', 'PasswordController@resetPassword');
$api = app('Dingo\Api\Routing\Router');

/*
|--------------------------------------------------------------------------
| Version Reminder
|--------------------------------------------------------------------------
|
| When next version comes add a prefix to the old version
| routes and change API_PREFIX in api.php file to null
|
|
*/
$api->version('v1', function ($api) {
    /*API*/
    $api->post('continue-with-kit', 'App\Http\Controllers\FacebookController@continueWithKit');
    $api->post('continue-with-facebook', 'App\Http\Controllers\FacebookController@continueWithFacebook');
    $api->post('login', 'App\Http\Controllers\Auth\LoginController@login');
    $api->post('register', 'App\Http\Controllers\Auth\RegistrationController@register');
    $api->post('send-password-reset-email', 'App\Http\Controllers\Auth\PasswordController@sendResetPasswordEmail');
    $api->post('reset-password', 'App\Http\Controllers\Auth\PasswordController@resetPassword');

    $api->get('authenticate', 'App\Http\Controllers\AccountController@checkForAuthentication');

    $api->post('account', 'App\Http\Controllers\AccountController@encryptData');
    $api->get('decrypt', 'App\Http\Controllers\AccountController@decryptData');

    $api->post('register-mobile', 'App\Http\Controllers\Auth\RegistrationController@registerWithMobile');
    $api->post('register-email', 'App\Http\Controllers\Auth\RegistrationController@registerWithEmail');
    $api->post('register-with-facebook', 'App\Http\Controllers\Auth\RegistrationController@registerWithFacebook');
    $api->post('login-with-kit', 'App\Http\Controllers\Auth\LoginController@loginWithKit');
//    $api->post('forget-password', 'App\Http\Controllers\Auth\PasswordController@sendResetPasswordEmail');

    $api->post('info', 'App\Http\Controllers\ShebaController@getInfo');
    $api->get('images', 'App\Http\Controllers\ShebaController@getImages');
    $api->get('locations', 'App\Http\Controllers\LocationController@getAllLocations');
    $api->get('search', 'App\Http\Controllers\SearchController@getService');
    $api->get('category-service', 'App\Http\Controllers\CategoryServiceController@getCategoryServices');
    $api->get('{service}/similar-services', 'App\Http\Controllers\CategoryServiceController@getSimilarServices');
    $api->get('job-times', 'App\Http\Controllers\JobController@getPreferredTimes');
    $api->get('cancel-job-reasons', 'App\Http\Controllers\JobController@cancelJobReasons');
    $api->get('images', 'App\Http\Controllers\ShebaController@getImages');
    $api->post('voucher-valid', 'App\Http\Controllers\CheckoutController@checkForValidity');

    $api->get('offers', 'App\Http\Controllers\ShebaController@getOffers');
    $api->get('offer/{offer}', 'App\Http\Controllers\ShebaController@getOffer');
    $api->get('offer/{offer}/similar-offer', 'App\Http\Controllers\ShebaController@getSimilarOffer');


    $api->post('career', 'App\Http\Controllers\CareerController@apply');

    $api->group(['prefix' => 'category'], function ($api) {
        $api->get('/', 'App\Http\Controllers\CategoryController@index');
        $api->get('{category}/secondary-categories', 'App\Http\Controllers\CategoryController@getChildren');
        $api->get('{category}/services', 'App\Http\Controllers\CategoryController@getServices');
        $api->get('{category}/parent', 'App\Http\Controllers\CategoryController@getParent');
    });
    $api->group(['prefix' => 'service'], function ($api) {
        $api->get('{service}/valid', 'App\Http\Controllers\ServiceController@validService');
        $api->get('{service}/get-prices', 'App\Http\Controllers\ServiceController@getPrices');
        $api->get('{service}/partners', 'App\Http\Controllers\ServiceController@getPartners');
        $api->get('{service}/location/{location}/partners', 'App\Http\Controllers\ServiceController@getPartners');
        $api->post('{service}/location/{location}/partners', 'App\Http\Controllers\ServiceController@getPartners');
        $api->post('{service}/{location}/change-partner', 'App\Http\Controllers\ServiceController@changePartner');
        $api->get('/{service}/reviews', 'App\Http\Controllers\ServiceController@getReviews');
        //For Back-end
        $api->post('{service}/change-partner', 'App\Http\Controllers\ServiceController@changePartnerWithoutLocation');
    });
    $api->group(['prefix' => 'partner'], function ($api) {
        $api->get('/', 'App\Http\Controllers\PartnerController@index');
        $api->get('{partner}/services', 'App\Http\Controllers\PartnerController@getPartnerServices');
        $api->get('{partner}/reviews', 'App\Http\Controllers\PartnerController@getReviews');
    });

    $api->group(['prefix' => 'checkout'], function ($api) {
        $api->get('place-order-final', 'App\Http\Controllers\CheckoutController@placeOrderFinal');
        $api->get('sp-payment-final', 'App\Http\Controllers\CheckoutController@spPaymentFinal');
    });

    $api->group(['prefix' => 'customer', 'middleware' => ['customer.auth']], function ($api) {
        $api->get('{customer}', 'App\Http\Controllers\CustomerController@getCustomerInfo');
        $api->get('{customer}/general-info', 'App\Http\Controllers\CustomerController@getCustomerGeneralInfo');
        $api->get('{customer}/intercom-info', 'App\Http\Controllers\CustomerController@getIntercomInfo');
        $api->get('{customer}/order-list', 'App\Http\Controllers\OrderController@getNotClosedOrderInfo');
        $api->get('{customer}/order-history', 'App\Http\Controllers\OrderController@getClosedOrderInfo');
        $api->get('{customer}/cancel-order-list', 'App\Http\Controllers\OrderController@getCancelledOrders');
        $api->get('{customer}/get-referral', 'App\Http\Controllers\CustomerController@getReferral');
        $api->post('{customer}/send-referral-request-email', 'App\Http\Controllers\CustomerController@sendReferralRequestEmail');
        $api->get('{customer}/get-promo', 'App\Http\Controllers\PromotionController@getPromo');
        $api->post('{customer}/add-promo', 'App\Http\Controllers\PromotionController@addPromo');
        $api->post('{customer}/suggest-promo', 'App\Http\Controllers\PromotionController@suggestPromo');

        $api->post('{customer}/sp-payment', 'App\Http\Controllers\CheckoutController@spPayment');
        $api->post('{customer}/order-valid', 'App\Http\Controllers\OrderController@checkOrderValidity');
        $api->post('{customer}/modify-review', 'App\Http\Controllers\ReviewController@modifyReview');
        $api->get('{customer}/job/{job}', 'App\Http\Controllers\JobController@getInfo');
        $api->post('{customer}/{job}/cancel-job', 'App\Http\Controllers\JobController@cancelJob');

        $api->post('{customer}/ask-quotation', 'App\Http\Controllers\CustomOrderController@askForQuotation');
        $api->get('{customer}/custom-order', 'App\Http\Controllers\CustomOrderController@getCustomOrders');
        $api->get('{customer}/custom-order/{custom_order}/quotation', 'App\Http\Controllers\CustomOrderController@getCustomOrderQuotation');
        $api->get('{customer}/custom-order/{custom_order}/discussion', 'App\Http\Controllers\CustomOrderController@getCommentForDiscussion');
        $api->post('{customer}/custom-order/{custom_order}/discussion', 'App\Http\Controllers\CustomOrderController@postCommentOnDiscussion');

        $api->post('{customer}/checkout/place-order', 'App\Http\Controllers\CheckoutController@placeOrder');
        $api->post('{customer}/checkout/place-order-with-online-payment', 'App\Http\Controllers\CheckoutController@placeOrderWithPayment');

        $api->post('{customer}/fb-integration', 'App\Http\Controllers\CustomerController@facebookIntegration');
        $api->post('{customer}/change-address', 'App\Http\Controllers\CustomerController@changeAddress');
        $api->post('{customer}/add-delivery-address', 'App\Http\Controllers\CustomerController@addDeliveryAddress');
        $api->get('{customer}/get-delivery-info', 'App\Http\Controllers\CustomerController@getDeliveryInfo');
        $api->post('{customer}/remove-address', 'App\Http\Controllers\CustomerController@removeDeliveryAddress');
        $api->post('{customer}/mobile', 'App\Http\Controllers\CustomerController@modifyMobile');
        $api->post('{customer}/add-secondary-mobile', 'App\Http\Controllers\CustomerController@addSecondaryMobile');
        $api->post('{customer}/remove-secondary-mobile', 'App\Http\Controllers\CustomerController@removeSecondaryMobile');
        $api->post('{customer}/set-primary-mobile', 'App\Http\Controllers\CustomerController@setPrimaryMobile');
        $api->post('{customer}/email', 'App\Http\Controllers\CustomerController@modifyEmail');
        $api->post('{customer}/email-verification', 'App\Http\Controllers\CustomerController@checkEmailVerification');
        $api->post('{customer}/send-verification-link', 'App\Http\Controllers\CustomerController@sendVerificationLink');

    });

    $api->post('rating', 'App\Http\Controllers\ReviewController@giveRatingFromEmail');

    $api->group(['prefix' => 'business'], function ($api) {
        $api->get('check-url', 'App\Http\Controllers\BusinessController@checkURL');
        $api->get('type-category', 'App\Http\Controllers\BusinessController@getTypeAndCategories');

        $api->group(['prefix' => 'member', 'middleware' => ['member.auth']], function ($api) {
            $api->get('/{member}/get-info', 'App\Http\Controllers\MemberController@getInfo');
            $api->get('/{member}/get-profile-info', 'App\Http\Controllers\MemberController@getProfileInfo');
            $api->post('/{member}/update-personal-info', 'App\Http\Controllers\MemberController@updatePersonalInfo');
            $api->post('/{member}/update-professional-info', 'App\Http\Controllers\MemberController@updateProfessionalInfo');
            $api->post('/{member}/change-NID', 'App\Http\Controllers\MemberController@changeNID');

            $api->post('/{member}/create-business', 'App\Http\Controllers\BusinessController@create');
            $api->post('{member}/check-business', 'App\Http\Controllers\BusinessController@checkBusiness');
            $api->get('/{member}/show', 'App\Http\Controllers\BusinessController@show');

            $api->get('{member}/business/{business}', 'App\Http\Controllers\BusinessController@getBusiness');
            $api->post('{member}/business/{business}/update', 'App\Http\Controllers\BusinessController@update');
            $api->post('{member}/business/{business}/change-logo', 'App\Http\Controllers\BusinessController@changeLogo');
            $api->get('{member}/business/{business}/members', 'App\Http\Controllers\BusinessController@getMembers');
            $api->get('{member}/business/{business}/requests', 'App\Http\Controllers\BusinessController@getRequests');
            $api->post('{member}/business/{business}/manage-invitation', 'App\Http\Controllers\BusinessController@manageInvitation');
            $api->get('{member}/business/{business}/get-member', 'App\Http\Controllers\BusinessMemberController@getMember');
            $api->post('{member}/business/{business}/change-member-type', 'App\Http\Controllers\BusinessMemberController@changeMemberType');


            $api->post('{member}/search', 'App\Http\Controllers\SearchController@searchBusinessOrMember');
            $api->get('{member}/requests', 'App\Http\Controllers\MemberController@getRequests');

            $api->post('{member}/send-invitation', 'App\Http\Controllers\InvitationController@sendInvitation');
            $api->post('{member}/manage-invitation', 'App\Http\Controllers\MemberController@manageInvitation');
        });
    });

    $api->group(['prefix' => 'affiliate/{affiliate}', 'middleware' => ['affiliate.auth']], function ($api) {
        $api->post('edit', 'App\Http\Controllers\AffiliateController@edit');
        $api->post('update-profile-picture', 'App\Http\Controllers\AffiliateController@updateProfilePic');

        $api->get('wallet', 'App\Http\Controllers\AffiliateController@getWallet');
        $api->get('affiliations', 'App\Http\Controllers\AffiliationController@index');
        $api->post('affiliations', 'App\Http\Controllers\AffiliationController@create');

    });

});