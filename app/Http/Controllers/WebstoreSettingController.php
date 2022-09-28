<?php

namespace App\Http\Controllers;

use App\Sheba\WebstoreSetting\WebstoreSettingService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Sheba\FileManagers\CdnFileManager;
use Sheba\FileManagers\FileManager;
use Sheba\ModificationFields;


class WebstoreSettingController extends Controller
{

    use ModificationFields, FileManager, CdnFileManager;

    public function __construct(WebstoreSettingService $webstoreSettingService)
    {
        $this->webstoreSettingService = $webstoreSettingService;
    }

    public function index(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $settings = $this->webstoreSettingService->getallSettings($partner->id);
        return api_response($request, null, 200, ['message' => 'Successful', 'data' => $settings]);
    }

    public function getThemeDetails($partner, Request $request)
    {
        $settings = $this->webstoreSettingService->getThemeDetails($partner);
        return api_response($request, null, 200, $settings);
    }

    public function store(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $this->webstoreSettingService
            ->setPartner($partner->id)
            ->setTheme($request->theme_id)
            ->setSettings($request->settings)
            ->store();
        return api_response($request, null, 200);
    }

    public function update(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $this->webstoreSettingService
            ->setPartner($partner->id)
            ->setTheme($request->theme_id)
            ->setSettings($request->settings)
            ->update();
        return api_response($request, null, 200);
    }

    public function storeSocialSetting(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $response = $this->webstoreSettingService
            ->setPartner($partner->id)
            ->setFacebook($request->facebook)
            ->setInstagram($request->instagram)
            ->setWhatsapp($request->whatsapp)
            ->setYoutube($request->youtube)
            ->setEmail($request->email)
            ->storeSocialSetting();
        return $response;
    }

    public function updateSocialSetting(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $response = $this->webstoreSettingService
            ->setPartner($partner->id)
            ->setFacebook($request->facebook)
            ->setInstagram($request->instagram)
            ->setWhatsapp($request->whatsapp)
            ->setYoutube($request->youtube)
            ->setEmail($request->email)
            ->updateSocialSetting();
        return $response;
    }

    public function getSocialSetting(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $socialSettings = $this->webstoreSettingService->getPartnerSocialSettings($partner->id);
        return api_response($request, null, 200, ['message' => 'Successful', 'data' => $socialSettings]);
    }

    public function getSystemDefinedSettings(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $systemDefinedSettings = $this->webstoreSettingService->getSystemDefinedSettings($partner->id);
        return api_response($request, null, 200, ['message' => 'Successful', 'data' => $systemDefinedSettings]);
    }

    public function getBannerList(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $banners =   $this->webstoreSettingService->setPartner($partner->id)->setType($request->type)->getBannerList();
        return http_response($request, null, 200, ['message' => 'Successful', 'banners' => $banners]);

    }

    public function getPageDetails(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $pageDetails = $this->webstoreSettingService->setPartner($partner->id)->setType($request->type)->getPageDetails();
        return http_response($request, null, 200, ['message' => 'Successful', 'page-settings' => $pageDetails]);
    }

    public function storePageSettings(Request $request)
    {
        $partner = $request->auth_user->getPartner();
        $banner_image_link = null;
        if ($request->file('image')) $banner_image_link = $this->createBannerForPageSettings($request->file('image'), removeRestrictedCharacters($request->banner_title));
        $this->webstoreSettingService->setPartner($partner->id)->setType($request->type)->setBannerId($request->banner_id)->setTitle($request->title)->setDescription($request->description)->setIsPublished($request->is_published)->setBannerImageLink($banner_image_link)->storePageSettings();
        return http_response($request, null, 200, ['message' => 'Successful']);
    }
    public function createBannerForPageSettings($file, $filename)
    {
        /** @var UploadedFile $avatar */
        /** @var string $avatar_filename */
        list($avatar, $avatar_filename) = $this->makeWebstoreBanner($file, $filename);
        return $this->saveFileToCDN($avatar, getWebstoreBannerFolder(), $avatar_filename);
    }

    protected function makeWebstoreBanner($file, $name): array
    {
        $filename = $this->uniqueFileName($file, $name);
        return [$file, $filename];
    }




}
