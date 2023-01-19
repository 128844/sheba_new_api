<?php

use Intervention\Image\File;
use Sheba\FileManagers\ImageResizer;
use Sheba\FileManagers\ImageSize;
use Sheba\FileManagers\S3Image;

if (!function_exists('getEmployeesImagesFolder')) {

    /**
     * Get Employee's Images Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getEmployeesImagesFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/employees_avatar/';
    }
}

if (!function_exists('getUserDefaultAvatar')) {

    /**
     * Get user default avatar file name.
     *
     * @return string
     */
    function getUserDefaultAvatar()
    {
        return getEmployeesImagesFolder(true) . 'default_user.jpg';
    }
}

if (!function_exists('getCategoryBannerFolder')) {

    /**
     * Get Category Banner Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/categories_images/banners/';
    }
}

if (!function_exists('getCategoryIconFolder')) {

    /**
     * Get Category Icon Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryIconFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/categories_images/icons/';
    }
}

if (!function_exists('getCategoryIconPngFolder')) {

    /**
     * Get Category IconPng Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryIconPngFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/categories_images/icons_png/';
    }
}

if (!function_exists('getCategoryGroupBannerFolder')) {

    /**
     * Get Category Group Banner Folder
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryGroupBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/category_groups_images/banners/';
    }
}

if (!function_exists('getCategoryGroupAppBannerFolder')) {

    /**
     * Get Category Group App Banner Folder
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryGroupAppBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/category_groups_images/app_banners/';
    }
}

if (!function_exists('getCategoryGroupThumbFolder')) {
    /**
     * Get Category Group Thumb Folder
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryGroupThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/category_groups_images/thumbs/';
    }
}

if (!function_exists('getCategoryGroupAppThumbFolder')) {
    /**
     * Get Category Group App Thumb Folder
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryGroupAppThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/category_groups_images/app_thumbs/';
    }
}

if (!function_exists('getCategoryGroupIconFolder')) {
    /**
     * Get Category Group App Thumb Folder
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryGroupIconFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/category_groups_images/icons/';
    }
}

if (!function_exists('getCategoryGroupIconPngFolder')) {
    /**
     * Get Category Group App Thumb Folder
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryGroupIconPngFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/category_groups_images/icons_png/';
    }
}

if (!function_exists('getCategoryDefaultBanner')) {

    /**
     * Get Category default Banner file name.
     *
     * @return string
     */
    function getCategoryDefaultBanner()
    {
        return getCategoryBannerFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getCategoryThumbFolder')) {

    /**
     * Get Category Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCategoryThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/categories_images/thumbs/';
    }
}

if (!function_exists('getCategoryDefaultThumb')) {

    /**
     * Get Category default Thumb file name.
     *
     * @return string
     */
    function getCategoryDefaultThumb()
    {
        return getCategoryThumbFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getServiceBannerFolder')) {

    /**
     * Get Service Banner Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getServiceBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/services_images/banners/';
    }
}

if (!function_exists('getServiceDefaultBanner')) {

    /**
     * Get Service default Banner file name.
     *
     * @return string
     */
    function getServiceDefaultBanner()
    {
        return getServiceBannerFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getServiceThumbFolder')) {

    /**
     * Get Service Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getServiceThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/services_images/thumbs/';
    }
}

if (!function_exists('getServiceDefaultThumb')) {

    /**
     * Get Service default Thumb file name.
     *
     * @return string
     */
    function getServiceDefaultThumb()
    {
        return getServiceThumbFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getPartnerLogoFolder')) {

    /**
     * Get Partner Logo Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getPartnerLogoFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/partners/logos/';
    }
}

if (!function_exists('getPartnerChequeBookImageFolder')) {

    /**
     * Get Partner Logo Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getPartnerChequeBookImageFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/partners/cheque_receipt/';
    }
}

if (!function_exists('getEmiBankIconsFolder')) {

    /**
     * Get Partner Logo Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getEmiBankIconsFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/emi_bank_icon/';
    }
}

if (!function_exists('getPartnerPackageFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getPartnerPackageFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');
        return $url . 'images/partners/package/';
    }
}

if (!function_exists('getEmiBankIconsFolder')) {

    /**
     * Get Partner Logo Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getEmiBankIconsFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/emi_bank_icon/';
    }
}

if (!function_exists('getPartnerDefaultLogo')) {

    /**
     * Get Partner default Logo file name.
     *
     * @return string
     */
    function getPartnerDefaultLogo()
    {
        return getPartnerLogoFolder(true) . 'default.png';
    }
}

if (!function_exists('getPartnerBadgesFolder')) {

    /**
     * Get Partner Badges Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getPartnerBadgesFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/badges/partners/';
    }
}

if (!function_exists('getCustomerBadgesFolder')) {

    /**
     * Get Customer Badges Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCustomerBadgesFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/badges/customers/';
    }
}

if (!function_exists('getResourceAvatarFolder')) {

    /**
     * Get Resource's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getResourceAvatarFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/resources/avatar/';
    }
}

if (!function_exists('getResourceDefaultAvatar')) {

    /**
     * Get resource default avatar file name.
     *
     * @return string
     */
    function getResourceDefaultAvatar()
    {
        return getResourceAvatarFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getResourceNIDFolder')) {

    /**
     * Get Resource's NID Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getResourceNIDFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/resources/nid/';
    }
}

if (!function_exists('getNIDFolder')) {

    /**
     * Get Profile's NID Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getNIDFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/profiles/nid/';
    }
}

if (!function_exists('getPushNotificationFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getPushNotificationFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/push_notification/';
    }
}

if (!function_exists('getCustomerAvatarFolder')) {

    /**
     * Get Customer's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCustomerAvatarFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/customer/avatar/';
    }
}

if (!function_exists('getCustomerDefaultAvatar')) {

    /**
     * Get customer default avatar file name.
     *
     * @return string
     */
    function getCustomerDefaultAvatar()
    {
        return getCustomerAvatarFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getSliderImagesFolder')) {

    /**
     * Get Slider Images Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getSliderImagesFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/slides/';
    }
}

if (!function_exists('getBankStatementImagesFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getBankStatementImagesFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/profiles/bank_statement_';
    }
}

if (!function_exists('getBankStatementDefaultImage')) {

    /**
     * @return string
     */
    function getBankStatementDefaultImage()
    {
        return getBankStatementImagesFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getTradeLicenceImagesFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getTradeLicenceImagesFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/profiles/trade_license_attachment_';
    }
}

if (!function_exists('getLoanFolder')) {
    /**
     * @param false $with_base_url
     * @return string
     */
    function getLoanFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) {
            $url = env('S3_URL');
        }
        return $url . 'images/profiles/loan_documents/';
    }
}

if (!function_exists('getNeoBankingFolder')) {
    function getNeoBankingFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) {
            $url = env('S3_URL');
        }
        return $url . 'images/profiles/neo_banking_documents/';
    }
}

if (!function_exists('getTradeLicenceDocumentsFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getTradeLicenceDocumentsFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'partner/trade_license/trade_';
    }
}

if (!function_exists('getLoanDocumentFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getLoanDocumentsFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'partner/loans/temp';
    }
}

if (!function_exists('getTradeLicenseDefaultImage')) {

    /**
     * @return string
     */
    function getTradeLicenseDefaultImage()
    {
        return getTradeLicenceImagesFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getFileTypeIcon')) {
    /**
     * Get icon for a file type.
     *
     * @param  $type
     * @return string
     */
    function getFileTypeIcon($type)
    {
        $type = strtolower($type);
        $file_type_icons = [
            'xls' => 'file-excel-o', 'xlsx' => 'file-excel-o', 'csv' => 'file-excel-o', 'ppt' => 'file-powerpoint-o', 'pptx' => 'file-powerpoint-o', 'docx' => 'file-word-o', 'doc' => 'file-word-o', 'odt' => 'file-word-o', 'rtf' => 'file-word-o', 'txt' => 'file-text-o', 'pdf' => 'file-pdf-o', 'rar' => 'file-archive-o', 'zip' => 'file-archive-o', 'jpg' => 'file-image-o', 'jpeg' => 'file-image-o', 'png' => 'file-image-o', 'gif' => 'file-image-o', 'mp4' => 'file-video-o', 'mp3' => 'file-audio-o', 'wma' => 'file-audio-o'
        ];

        if (!array_key_exists($type, $file_type_icons)) {
            return 'file-o';
        }

        return $file_type_icons[$type];
    }
}

if (!function_exists('getOfferBannerFolder')) {

    /**
     * Get Offer Banner Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getOfferBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/offers_images/banners/';
    }
}

if (!function_exists('getOfferThumbFolder')) {

    /**
     * Get Category Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getOfferThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/offers_images/thumbs/';
    }
}

if (!function_exists('getBusinessLogoFolder')) {

    /**
     * Get Business Logo Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getBusinessLogoFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/partners/logos/';
    }
}

if (!function_exists('getBusinessDefaultLogo')) {

    /**
     * Get Business default Logo file name.
     *
     * @return string
     */
    function getBusinessDefaultLogo()
    {
        return getBusinessLogoFolder(true) . 'default.png';
    }
}

if (!function_exists('getMemberAvatarFolder')) {

    /**
     * Get Member's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getMemberAvatarFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/resources/avatar/';
    }
}

if (!function_exists('getMemberDefaultAvatar')) {

    /**
     * Get resource default avatar file name.
     *
     * @return string
     */
    function getMemberDefaultAvatar()
    {
        return getMemberAvatarFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getMemberNIDFolder')) {

    /**
     * Get Member's NID Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getMemberNIDFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/resources/nid/';
    }
}

if (!function_exists('getNotificationFolder')) {

    /**
     * getNotificationFolder.
     *
     * @return string
     */
    function getNotificationFolder()
    {
        return URL::to('assets/audio/notification');
    }
}

if (!function_exists('getNotificationFileName')) {

    /**
     * getNotificationFileName
     *
     * @param string $sound
     * @return string
     */
    function getNotificationFileName($sound)
    {
        return getNotificationFolder() . '/' . $sound . '.mp3';
    }
}

if (!function_exists('getBase64FileExtension')) {

    /**
     * getBase64FileExtension
     *
     * @param $file
     * @return string
     */
    function getBase64FileExtension($file)
    {
        return image_type_to_extension(getimagesize($file)[2], false);
    }
}

if (!function_exists('getProfileDefaultAvatar')) {

    /**
     * Get resource default avatar file name.
     *
     * @return string
     */
    function getProfileDefaultAvatar()
    {
        return getProfileAvatarFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getProfileAvatarFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getProfileAvatarFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/profiles/avatar/';
    }
}

if (!function_exists('getFileName')) {

    /**
     * @param $file
     * @return mixed|string
     */
    function getFileName($file)
    {
        $extension = explode("/", $file);
        return end($extension);
    }
}

if (!function_exists('getFileExtension')) {

    /**
     * @param $file
     * @return mixed|string
     */
    function getFileExtension($file)
    {
        $extension = explode(".", $file);
        return end($extension);
    }
}

if (!function_exists('getRewardShopDefaultThumb')) {

    /**
     * Get Reward Shop default Thumb file name.
     *
     * @return string
     */
    function getRewardShopDefaultThumb()
    {
        return getRewardShopThumbFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getRewardShopDefaultBanner')) {

    /**
     * Get Reward Shop default Banner file name.
     *
     * @return string
     */
    function getRewardShopDefaultBanner()
    {
        return getRewardShopBannerFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getRewardShopThumbFolder')) {

    /**
     * Get Reward Shop Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getRewardShopThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'images/reward_product_images/thumbs/';
    }
}

if (!function_exists('getRewardShopBannerFolder')) {

    /**
     * Get Service Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getRewardShopBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'images/reward_product_images/banners/';
    }
}

if (!function_exists('getBulkTopUpFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getBulkTopUpFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'bulk_top_ups/';
    }
}

if (!function_exists('getLeaveAdjustmentFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getLeaveAdjustmentFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'Leave_adjustment/';
    }
}

if (!function_exists('getBulkLeaveAdjustmentFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getBulkLeaveAdjustmentFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'bulk_Leave_adjustment/';
    }
}

if (!function_exists('getBulkGrossSalaryFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getBulkGrossSalaryFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'bulk_gross_salary_folder/';
    }
}

if (!function_exists('getBulkVendorStoreFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getBulkVendorStoreFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'bulk_vendors_store/';
    }
}

if (!function_exists('getPosCategoryDefaultThumb')) {

    /**
     * Get Category default Thumb file name.
     *
     * @return string
     */
    function getPosCategoryDefaultThumb()
    {
        return getPosCategoryThumbFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getPosCategoryThumbFolder')) {

    /**
     * Get Category Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getPosCategoryThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'images/pos/categories/thumbs/';
    }
}

if (!function_exists('getPosCategoryDefaultBanner')) {

    /**
     * Get Category default Thumb file name.
     *
     * @return string
     */
    function getPosCategoryDefaultBanner()
    {
        return getPosCategoryBannerFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getPosCategoryBannerFolder')) {

    /**
     * Get Category Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getPosCategoryBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'images/pos/categories/banners/';
    }
}

if (!function_exists('getPosServiceDefaultThumb')) {

    /**
     * Get Service default Thumb file name.
     *
     * @return string
     */
    function getPosServiceDefaultThumb()
    {
        return getPosServiceThumbFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getPosServiceThumbFolder')) {

    /**
     * Get Service Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getPosServiceThumbFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'images/pos/services/thumbs/';
    }
}

if (!function_exists('getPosServiceDefaultBanner')) {

    /**
     * Get Service default Thumb file name.
     *
     * @return string
     */
    function getPosServiceDefaultBanner()
    {
        return getPosServiceBannerFolder(true) . 'default.jpg';
    }
}

if (!function_exists('getPosServiceBannerFolder')) {

    /**
     * Get Service Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getPosServiceBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'images/pos/services/banners/';
    }
}

if (!function_exists('getWebstoreBannerFolder')) {

    /**
     * Get Service Thumb Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getWebstoreBannerFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'webstore/banners/';
    }
}

if (!function_exists('getVatRegistrationImagesFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getVatRegistrationImagesFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/partner/vat_registration/vat_';
    }
}

if (!function_exists('getVatRegistrationDocumentsFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getVatRegistrationDocumentsFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'partner/vat_registration/vat_';
    }
}

if (!function_exists('getDueTrackerAttachmentsFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getDueTrackerAttachmentsFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'partner/due-list-attachments/';
    }
}

if (!function_exists('getPosServiceImageGalleryFolder')) {
    function getPosServiceImageGalleryFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'partner/pos-service-image-gallery/';
    }
}

if (!function_exists('getCoWorkerInviteErrorFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCoWorkerInviteErrorFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'co_worker_invite_error/';
    }
}

if (!function_exists('getCoWorkerStatusChangeErrorFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getCoWorkerStatusChangeErrorFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'co_worker_status_change_error/';
    }
}

if (!function_exists('getPartnerProofOfBusinessFolder')) {
    /**
     * @param false $with_base_url
     * @param int $partner_id
     * @return string
     */
    function getPartnerProofOfBusinessFolder($with_base_url = false, $partner_id = 0): string
    {
        $url = '';
        if ($with_base_url)
            $url = env('S3_URL');
        return $url . "partner/$partner_id/proof-of-business";
    }
}

if (!function_exists('getResizedUrls')) {
    /**
     * @param $url
     * @param $height
     * @param $width
     * @return array
     */
    function getResizedUrls($url, $height, $width)
    {
        if (!$url) return [];

        $resizer = getResizer($url, $height, $width);
        $resizer->buildUrls();
        return [
            "webp" => $resizer->getWebpUrls(),
            "original" => $resizer->getOriginalExtUrls()
        ];
    }
}

if (!function_exists('resizeAndSaveImage')) {
    /**
     * @param $url
     * @param $height
     * @param $width
     * @param null $image
     */
    function resizeAndSaveImage($url, $height, $width, $image = null)
    {
        $resizer = getResizer($url, $height, $width, $image);
        $resizer->resizeAndSave();
    }
}

if (!function_exists('getResizer')) {
    /**
     * @param $url
     * @param $height
     * @param $width
     * @param null $image
     * @return ImageResizer
     */
    function getResizer($url, $height, $width, $image = null)
    {
        $s3_image = new S3Image($url);
        if ($image) $s3_image->setImage($image);

        /** @var ImageResizer $resizer */
        $resizer = app(ImageResizer::class);
        $resizer
            ->setImage($s3_image)
            ->pushSize(new ImageSize($height, $width));

        return $resizer;
    }
}

if (!function_exists('getTempDownloadFolder')) {
    /**
     * @return string
     */
    function getTempDownloadFolder()
    {
        return public_path('temp/downloads') . "/";
    }
}

if (!function_exists('getExtensionFromMime')) {
    /**
     * @param $mime
     * @return string
     */
    function getExtensionFromMime($mime)
    {
        $mime_map = [
            'video/3gpp2' => '3g2',
            'video/3gp' => '3gp',
            'video/3gpp' => '3gp',
            'application/x-compressed' => '7zip',
            'audio/x-acc' => 'aac',
            'audio/ac3' => 'ac3',
            'application/postscript' => 'ai',
            'audio/x-aiff' => 'aif',
            'audio/aiff' => 'aif',
            'audio/x-au' => 'au',
            'video/x-msvideo' => 'avi',
            'video/msvideo' => 'avi',
            'video/avi' => 'avi',
            'application/x-troff-msvideo' => 'avi',
            'application/macbinary' => 'bin',
            'application/mac-binary' => 'bin',
            'application/x-binary' => 'bin',
            'application/x-macbinary' => 'bin',
            'image/bmp' => 'bmp',
            'image/x-bmp' => 'bmp',
            'image/x-bitmap' => 'bmp',
            'image/x-xbitmap' => 'bmp',
            'image/x-win-bitmap' => 'bmp',
            'image/x-windows-bmp' => 'bmp',
            'image/ms-bmp' => 'bmp',
            'image/x-ms-bmp' => 'bmp',
            'application/bmp' => 'bmp',
            'application/x-bmp' => 'bmp',
            'application/x-win-bitmap' => 'bmp',
            'application/cdr' => 'cdr',
            'application/coreldraw' => 'cdr',
            'application/x-cdr' => 'cdr',
            'application/x-coreldraw' => 'cdr',
            'image/cdr' => 'cdr',
            'image/x-cdr' => 'cdr',
            'zz-application/zz-winassoc-cdr' => 'cdr',
            'application/mac-compactpro' => 'cpt',
            'application/pkix-crl' => 'crl',
            'application/pkcs-crl' => 'crl',
            'application/x-x509-ca-cert' => 'crt',
            'application/pkix-cert' => 'crt',
            'text/css' => 'css',
            'text/x-comma-separated-values' => 'csv',
            'text/comma-separated-values' => 'csv',
            'application/vnd.msexcel' => 'csv',
            'application/x-director' => 'dcr',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/x-dvi' => 'dvi',
            'message/rfc822' => 'eml',
            'application/x-msdownload' => 'exe',
            'video/x-f4v' => 'f4v',
            'audio/x-flac' => 'flac',
            'video/x-flv' => 'flv',
            'image/gif' => 'gif',
            'application/gpg-keys' => 'gpg',
            'application/x-gtar' => 'gtar',
            'application/x-gzip' => 'gzip',
            'application/mac-binhex40' => 'hqx',
            'application/mac-binhex' => 'hqx',
            'application/x-binhex40' => 'hqx',
            'application/x-mac-binhex40' => 'hqx',
            'text/html' => 'html',
            'image/x-icon' => 'ico',
            'image/x-ico' => 'ico',
            'image/vnd.microsoft.icon' => 'ico',
            'text/calendar' => 'ics',
            'application/java-archive' => 'jar',
            'application/x-java-application' => 'jar',
            'application/x-jar' => 'jar',
            'image/jp2' => 'jp2',
            'video/mj2' => 'jp2',
            'image/jpx' => 'jp2',
            'image/jpm' => 'jp2',
            'image/jpeg' => 'jpeg',
            'image/pjpeg' => 'jpeg',
            'application/x-javascript' => 'js',
            'application/json' => 'json',
            'text/json' => 'json',
            'application/vnd.google-earth.kml+xml' => 'kml',
            'application/vnd.google-earth.kmz' => 'kmz',
            'text/x-log' => 'log',
            'audio/x-m4a' => 'm4a',
            'audio/mp4' => 'm4a',
            'application/vnd.mpegurl' => 'm4u',
            'audio/midi' => 'mid',
            'application/vnd.mif' => 'mif',
            'video/quicktime' => 'mov',
            'video/x-sgi-movie' => 'movie',
            'audio/mpeg' => 'mp3',
            'audio/mpg' => 'mp3',
            'audio/mpeg3' => 'mp3',
            'audio/mp3' => 'mp3',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpeg',
            'application/oda' => 'oda',
            'audio/ogg' => 'ogg',
            'video/ogg' => 'ogg',
            'application/ogg' => 'ogg',
            'font/otf' => 'otf',
            'application/x-pkcs10' => 'p10',
            'application/pkcs10' => 'p10',
            'application/x-pkcs12' => 'p12',
            'application/x-pkcs7-signature' => 'p7a',
            'application/pkcs7-mime' => 'p7c',
            'application/x-pkcs7-mime' => 'p7c',
            'application/x-pkcs7-certreqresp' => 'p7r',
            'application/pkcs7-signature' => 'p7s',
            'application/pdf' => 'pdf',
            'application/octet-stream' => 'pdf',
            'application/x-x509-user-cert' => 'pem',
            'application/x-pem-file' => 'pem',
            'application/pgp' => 'pgp',
            'application/x-httpd-php' => 'php',
            'application/php' => 'php',
            'application/x-php' => 'php',
            'text/php' => 'php',
            'text/x-php' => 'php',
            'application/x-httpd-php-source' => 'php',
            'image/png' => 'png',
            'image/x-png' => 'png',
            'application/powerpoint' => 'ppt',
            'application/vnd.ms-powerpoint' => 'ppt',
            'application/vnd.ms-office' => 'ppt',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 'pptx',
            'application/x-photoshop' => 'psd',
            'image/vnd.adobe.photoshop' => 'psd',
            'audio/x-realaudio' => 'ra',
            'audio/x-pn-realaudio' => 'ram',
            'application/x-rar' => 'rar',
            'application/rar' => 'rar',
            'application/x-rar-compressed' => 'rar',
            'audio/x-pn-realaudio-plugin' => 'rpm',
            'application/x-pkcs7' => 'rsa',
            'text/rtf' => 'rtf',
            'text/richtext' => 'rtx',
            'video/vnd.rn-realvideo' => 'rv',
            'application/x-stuffit' => 'sit',
            'application/smil' => 'smil',
            'text/srt' => 'srt',
            'image/svg+xml' => 'svg',
            'application/x-shockwave-flash' => 'swf',
            'application/x-tar' => 'tar',
            'application/x-gzip-compressed' => 'tgz',
            'image/tiff' => 'tiff',
            'font/ttf' => 'ttf',
            'text/plain' => 'txt',
            'text/x-vcard' => 'vcf',
            'application/videolan' => 'vlc',
            'text/vtt' => 'vtt',
            'audio/x-wav' => 'wav',
            'audio/wave' => 'wav',
            'audio/wav' => 'wav',
            'application/wbxml' => 'wbxml',
            'video/webm' => 'webm',
            'image/webp' => 'webp',
            'audio/x-ms-wma' => 'wma',
            'application/wmlc' => 'wmlc',
            'video/x-ms-wmv' => 'wmv',
            'video/x-ms-asf' => 'wmv',
            'font/woff' => 'woff',
            'font/woff2' => 'woff2',
            'application/xhtml+xml' => 'xhtml',
            'application/excel' => 'xl',
            'application/msexcel' => 'xls',
            'application/x-msexcel' => 'xls',
            'application/x-ms-excel' => 'xls',
            'application/x-excel' => 'xls',
            'application/x-dos_ms_excel' => 'xls',
            'application/xls' => 'xls',
            'application/x-xls' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'application/vnd.ms-excel' => 'xlsx',
            'application/xml' => 'xml',
            'text/xml' => 'xml',
            'text/xsl' => 'xsl',
            'application/xspf+xml' => 'xspf',
            'application/x-compress' => 'z',
            'application/x-zip' => 'zip',
            'application/zip' => 'zip',
            'application/x-zip-compressed' => 'zip',
            'application/s-compressed' => 'zip',
            'multipart/x-zip' => 'zip',
            'text/x-scriptzsh' => 'zsh',
        ];

        return isset($mime_map[$mime]) ? $mime_map[$mime] : false;
    }
}

if (!function_exists('getFullNameWithoutExtension')) {
    /**
     * @param File $file
     * @return string
     */
    function getFullNameWithoutExtension(File $file)
    {
        return $file->dirname . "/" . $file->filename;
    }
}

if (!function_exists('getNameWithExtension')) {
    /**
     * @param $path
     * @return mixed|string
     */
    function getNameWithExtension($path)
    {
        $info = pathinfo($path);
        return $info['basename'];
    }
}

if (!function_exists('getStorageExportFolder')) {
    /**
     * @return string
     */
    function getStorageExportFolder(): string
    {
        return storage_path('exports') . "/";
    }
}

if (!function_exists('getAppVersionImageLinkFolder')) {

    /**
     * @param bool $with_base_url
     * @return string
     */
    function getAppVersionImageLinkFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = config('s3.url');

        return $url . 'images/app_version_images/';
    }
}

if (!function_exists('getEmployeeVisitFolder')) {

    /**
     * @param false $with_base_url
     * @return string
     */
    function getEmployeeVisitFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'images/employee_visit_images';
    }
}

if (!function_exists('getAttendanceReconciliationFolder')) {

    /**
     * @param $with_base_url
     * @return string
     */
    function getAttendanceReconciliationFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'attendance_reconciliation/';
    }
}

if (!function_exists('getBkashExcelFolder')) {

    /**
     * Get Profile's Avatar Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getBkashExcelFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'bkash_excel/';
    }
}

if (!function_exists('getBusinessMemberAdditionalFileFolder')) {

    /**
     * Get Business Member Additional Data File Folder.
     *
     * @param bool $with_base_url
     * @return string
     */
    function getBusinessMemberAdditionalFileFolder($with_base_url = false)
    {
        $url = '';
        if ($with_base_url) $url = env('S3_URL');

        return $url . 'uploads/business/member/additional/';
    }
}
