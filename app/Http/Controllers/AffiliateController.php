<?php

namespace App\Http\Controllers;

use App\Models\Affiliate;
use App\Repositories\FileRepository;
use Illuminate\Http\Request;
use Validator;
use App\Http\Requests;

class AffiliateController extends Controller
{
    private $fileRepository;

    private function __construct()
    {
        $this->fileRepository = new FileRepository();
    }

    public function edit(Request $request)
    {
        $affiliate = Affiliate::find($request->affiliate);
        if ($request->has('name')) {
            if ($request->name != '') {
                $profile = $affiliate->profile;
                $profile->name = $request->name;
                $profile->update();
            }
        }
        if ($request->has('bkash_no')) {
            if ($request->bkash_no != '' || $request->bkash_no != null) {
                $affiliate->banking_info = $request->bkash_no;
            }
        }
        if ($request->has('geolocation')) {
            if ($request->geolocation != '') {
                $affiliate->geolocation = $request->geolocation;
            }
        }
        return $affiliate->update() ? response()->json(['code' => 200]) : response()->json(['code' => 404]);
    }

    public function updateProfilePic(Request $request)
    {
        if ($msg = $this->_validateImage($request) != false) {
            return response()->json(['code' => 500, 'msg' => $msg]);
        }
        $photo = $request->file('photo');
        $profile = Affiliate::find($request->affiliate)->profile;
        if (strpos($profile->pro_pic, 'images/customer/avatar/default.jpg') == false) {
            $filename = substr($profile->pro_pic, strlen(env('S3_URL')));
            $this->fileRepository->deleteFileFromCDN($filename);
        }
        $profile->pro_pic = $this->fileRepository->uploadImage($profile, $request->file('photo'), 'images/profiles/', '.' . $photo->extension());
        return $profile->update() ? response()->json(['code' => 200, 'picture' => $profile->pro_pic]) : response()->json(['code' => 404]);
    }

    private function _validateImage($request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|mimes:jpeg,png|max:500'
        ]);
        return $validator->fails() ? $validator->errors()->all()[0] : false;
    }

}
