<?php namespace App\Sheba\Business\CoWorker\ProfileInformation;


use App\Repositories\FileRepository;
use Carbon\Carbon;
use Sheba\Repositories\Interfaces\BusinessMemberRepositoryInterface;
use Sheba\Repositories\Interfaces\MemberRepositoryInterface;
use Sheba\Repositories\Interfaces\ProfileRepositoryInterface;
use DB;

class PersonalInfoUpdater
{
    /*** @var MemberRepositoryInterface $memberRepository*/
    private $memberRepository;
    /*** @var ProfileRequester $profileRequester*/
    private $profileRequester;
    /** @var ProfileRepositoryInterface $profileRepository*/
    private $profileRepository;
    /*** @var BusinessMemberRepositoryInterface */
    private $businessMemberRepository;
    /*** @var FileRepository $fileRepository*/
    private $fileRepository;

    public function __construct()
    {
        $this->memberRepository = app(MemberRepositoryInterface::class);
        $this->profileRepository = app(ProfileRepositoryInterface::class);
        $this->businessMemberRepository = app(BusinessMemberRepositoryInterface::class);
        $this->fileRepository = app(FileRepository::class);
    }

    public function setProfileRequester(ProfileRequester $profile_requester)
    {
        $this->profileRequester = $profile_requester;
        return $this;
    }

    public function update()
    {
        DB::transaction(function (){
            $this->makeData();
        });
    }

    private function makeData()
    {
        $business_member = $this->profileRequester->getBusinessMember();
        $member = $business_member->member;
        $profile = $member->profile;
        $profile_data = $this->makeProfileData();
        $member_data = $this->makeMemberData();
        $business_member_data = $this->makeBusinessMemberData();
        $image_data = $this->storeImage($profile);

        if ($image_data) $profile_data = array_merge($profile_data, $image_data);
        if ($member_data) $this->memberRepository->update($member, $member_data);
        if ($profile_data) $this->profileRepository->update($profile, $profile_data);
        if ($business_member_data) $this->businessMemberRepository->update($business_member, $business_member_data);
    }

    private function makeProfileData()
    {
        $data = [];
        if ($this->profileRequester->getDateOfBirth()) $data['dob'] = $this->profileRequester->getDateOfBirth();
        if ($this->profileRequester->getAddress()) $data['address'] = $this->profileRequester->getAddress();
        if ($this->profileRequester->getNationality()) $data['nationality'] = $this->profileRequester->getNationality();
        if ($this->profileRequester->getNidNo()) $data['nid_no'] = $this->profileRequester->getNidNo();
        if ($this->profileRequester->getPassportNo()) $data['passport_no'] = $this->profileRequester->getPassportNo();
        if ($this->profileRequester->getBloodGroup()) $data['blood_group'] = $this->profileRequester->getBloodGroup();
        return $data;
    }

    private function makeMemberData()
    {
        return ['social_links' =>   $this->profileRequester->getSocialLinks()];
    }

    private function makeBusinessMemberData()
    {
        return $this->profileRequester->getMobile() ? [
          'mobile' => $this->profileRequester->getMobile()
        ] : null;
    }

    private function storeImage($profile)
    {
        $nid_front = $this->profileRequester->getNidFrontImage();
        $nid_back = $this->profileRequester->getNidBackImage();
        $passport_image = $this->profileRequester->getPassportImage();

        $image_data = [];

        if ($nid_front) {
            $image_data['nid_image_front'] = $this->getPicture($profile, $nid_front, 'nid_image_front');
        }
        if ($nid_back) {
            $image_data['nid_image_back'] = $this->getPicture($profile, $nid_back, 'nid_image_back');
        }
        if ($passport_image) {
            $image_data['passport_image'] = $this->getPicture($profile, $passport_image, 'passport');
        }

        return $image_data;
    }

    private function getPicture($profile, $photo, $image_for = 'pro_pic')
    {
        if (basename($profile->$image_for) != 'default.jpg') {
            $filename = substr($profile->{$image_for}, strlen(config('sheba.s3_url')));
            $this->deleteOldImage($filename);
        }

        return $this->fileRepository->uploadToCDN($this->makePicName($profile, $photo, $image_for), $photo, 'images/profiles/' . $image_for . '_');
    }

    private function deleteOldImage($filename)
    {
        $old_image = substr($filename, strlen(config('sheba.s3_url')));
        $this->fileRepository->deleteFileFromCDN($old_image);
    }

    private function makePicName($profile, $photo, $image_for = 'profile')
    {
        return Carbon::now()->timestamp . '_' . $image_for . '_image_' . $profile->id . '.' . $photo->extension();
    }

}
