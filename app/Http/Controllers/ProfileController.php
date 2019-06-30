<?php namespace App\Http\Controllers;

use App\Models\Profile;
use App\Repositories\FileRepository;
use App\Repositories\ProfileRepository;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Sheba\Helpers\Formatters\BDMobileFormatter;
use Sheba\Repositories\ProfileRepository as ShebaProfileRepository;
use Sheba\Sms\Sms;
use JWTAuth;
use JWTFactory;
use Validator;

class ProfileController extends Controller
{
    private $profileRepo;
    private $fileRepo;

    public function __construct(ProfileRepository $profile_repository, FileRepository $file_repository)
    {
        $this->profileRepo = $profile_repository;
        $this->fileRepo = $file_repository;
    }

    public function changePicture(Request $request)
    {
        if ($msg = $this->_validateImage($request)) {
            return response()->json(['code' => 500, 'msg' => $msg]);
        }
        $profile = $request->profile;
        $photo = $request->file('photo');
        if (basename($profile->pro_pic) != 'default.jpg') {
            $filename = substr($profile->pro_pic, strlen(env('S3_URL')));
            $this->fileRepo->deleteFileFromCDN($filename);
        }
        $filename = Carbon::now()->timestamp . '_profile_image_' . $profile->id . '.' . $photo->extension();
        $picture_link = $this->fileRepo->uploadToCDN($filename, $request->file('photo'), 'images/profiles/');
        if ($picture_link != false) {
            $profile->pro_pic = $picture_link;
            $profile->update();
            return response()->json(['code' => 200, 'picture' => $profile->pro_pic]);
        } else {
            return response()->json(['code' => 404]);
        }
    }

    private function _validateImage($request)
    {
        $validator = Validator::make($request->all(), [
            'photo' => 'required|mimes:jpeg,png'
        ]);
        return $validator->fails() ? $validator->errors()->all()[0] : false;
    }

    public function getProfile(Request $request)
    {
        if ($request->has('mobile') && $request->has('name')) {
            $mobile = formatMobile($request->mobile);
            $profile = $this->profileRepo->getIfExist($mobile, 'mobile');
            if ($request->has('email')) {
                $emailProfile = $this->profileRepo->getByEmail($request->email);
            }
            if (!$profile) {
                if (isset($emailProfile)) return api_response($request, null, 401, ['message' => 'Profile email and submitted email does not match']);
                $data = ['name' => $request->name, 'mobile' => $mobile];
                if ($request->has('nid_no') && !empty($request->nid_no)) $data['nid_no'] = $request->nid_no;
                if ($request->has('gender') && !empty($request->gender)) $data['gender'] = $request->gender;
                if ($request->has('dob') && !empty($request->dob)) $data['dob'] = $request->dob;
                if ($request->has('email') && !empty($request->email)) $data['email'] = $request->email;
                if ($request->has('password') && !empty($request->password)) $data['password'] = bcrypt($request->password);
                $profile = $this->profileRepo->store($data);
            } else {
                if (isset($emailProfile) && $emailProfile->id != $profile->id) {
                    return api_response($request, null, 401, ['message' => 'Profile email and submitted email does not match']);
                }
                if (empty($profile->email) && !empty($request->email)) {
                    $profile->email = $request->email;
                }
                if (empty($profile->password) && !empty($request->password)) {
                    $profile->password = bcrypt($request->password);
                }
                if (empty($profile->name)) {
                    $profile->name = $request->name;
                }
                $profile->save();
            }
        } elseif ($request->has('profile_id')) {
            $profile = $this->profileRepo->getIfExist($request->profile_id, 'id');
        } else {
            return api_response($request, null, 404, []);
        }

        if (!$profile) return api_response($request, null, 404, []);

        $profile = $profile->toArray();
        unset($profile['password']);
        return api_response($request, $profile, 200, ['info' => $profile]);
    }

    public function updateProfileDocument(Request $request, $id, ShebaProfileRepository $repository)
    {
        try {
            $profile = $request->profile;
            if (!$profile) return api_response($request, null, 404, ['message' => 'Profile no found']);
            $rules = ['pro_pic' => 'sometimes|string', 'nid_image_back' => 'sometimes|string', 'nid_image_front' => 'sometimes|string'];
            $this->validate($request, $rules);
            $data = $request->only(['email', 'name', 'password', 'pro_pic', 'nid_image_front', 'email', 'gender', 'dob', 'mobile', 'nid_no', 'address']);
            $data = array_filter($data, function ($item) {
                return $item != null;
            });
            if (!empty($data)) {
                $validation = $repository->validate($data, $profile);
                if ($validation === true) {
                    $repository->update($profile, $data);
                } elseif ($validation === 'phone') {
                    return api_response($request, null, 500, ['message' => 'Mobile number used by another user']);
                } elseif ($validation === 'email') {
                    return api_response($request, null, 500, ['message' => 'Email used by another user']);
                }
            } else {
                return api_response($request, null, 404, ['message' => 'No data provided']);
            }
            return api_response($request, null, 200, ['message' => 'Profile Updated']);
        } catch (ValidationException $e) {
            $message = getValidationErrorMessage($e->errors());
            return api_response($request, null, 401, ['message' => $message]);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500, ['message' => $e->getMessage(), 'trace' => $e->getTrace()]);
        }
    }

    public function forgetPassword(Request $request, Sms $sms)
    {
        $rules = ['mobile' => 'required|mobile:bd'];
        try {
            $this->validate($request, $rules);
            $mobile = BDMobileFormatter::format($request->mobile);
            $profile = Profile::where('mobile', $mobile)->first();
            if (!$profile) return api_response($request, null, 404, ['message' => 'Profile not found with this number']);
            $password = str_random(6);
            $smsSent = $sms->shoot($mobile, "Your password is reset to $password . Please use this password to login");
            $profile->update(['password' => bcrypt($password)]);
            return api_response($request, true, 200, ['message' => 'Your password is sent to your mobile number. Please use that password to login']);
        } catch (ValidationException $e) {
            return api_response($request, null, 401, ['message' => 'Invalid mobile number']);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }
    }

    public function getProfileInfoByMobile(Request $request)
    {
        try {
            $mobile = BDMobileFormatter::format($request->mobile);
            $profile = $this->profileRepo->getIfExist($mobile, 'mobile');;
            if (!$profile) return api_response($request, null, 404, ['message' => 'Profile not found with this number']);
            return api_response($request, true, 200, ['message' => 'Profile found', 'profile' => $profile]);
        } catch (ValidationException $e) {
            return api_response($request, null, 401, ['message' => 'Invalid mobile number']);
        } catch (\Throwable $e) {
            app('sentry')->captureException($e);
            return api_response($request, null, 500);
        }

    }

    public function getJWT(Request $request)
    {
        try {
            $token = $this->generateUtilityToken($request->profile);
            return api_response($request, $token, 200, ['token' => $token]);
        } catch (\Throwable $e) {
            return api_response($request, null, 500, ['message' => $e->getMessage()]);
        }
    }

    public function refresh(Request $request)
    {
        $token = JWTAuth::getToken();
        if (!$token) {
            return api_response($request, null, 401, ['message' => "Token is not present."]);
        }

        try {
            $token = JWTAuth::refresh($token);
        } catch (\Exception $e) {
            return api_response($request, null, 403, ['message' => $e->getMessage()]);
        }

        return api_response($request, $token, 200, ['token' => $token]);
    }

    private function generateUtilityToken(Profile $profile)
    {
        $from = \request()->get('from');
        $id = \request()->id;
        $customClaims = [
            'profile_id' => $profile->id,
            'customer_id' => $profile->customer ? $profile->customer->id : null,
            'affiliate_id' => $profile->affiliate ? $profile->affiliate->id : null,
            'from' => constants('AVATAR_FROM_CLASS')[$from],
            'user_id' => $id
        ];
        return JWTAuth::fromUser($profile, $customClaims);
    }
}
