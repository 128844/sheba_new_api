<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Resource;
use function GuzzleHttp\Promise\all;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Redis;
use JWTAuth;

class AccountController extends Controller
{
    public function checkForAuthentication(Request $request)
    {
        $key = Redis::get($request->input('access_token'));
        //key exists
        if ($key != null) {
            $info = json_decode($key);
            if ($info->avatar == 'customer') {
                $customer = Customer::find($info->id);
                $token = JWTAuth::fromUser($customer);
                Redis::del($request->input('access_token'));
                if ($customer->profile_id == $info->profile_id) {
                    return response()->json([
                        'msg' => 'successful', 'code' => 200, 'token' => $token,
                        'remember_token' => $customer->remember_token, 'customer' => $customer->id, 'customer_img' => $customer->pro_pic
                    ]);
                }
            } else if ($info->avatar == 'resource') {
                $resource = Resource::find($info->id);
                Redis::del($request->input('access_token'));
                if ($resource->profile_id == $info->profile_id) {
                    return response()->json([
                        'msg' => 'successful', 'code' => 200, 'resource' => $resource->id
                    ]);
                }
            }
        } else {
            return response()->json(['msg' => 'not found', 'code' => 404]);
        }
    }

    public function encryptData(Request $request)
    {
        try {
            $encrypted = Crypt::encryptString(json_encode($request->all()));
            return response()->json(['code' => 200, 'token' => $encrypted]);
        } catch (DecryptException $e) {
            return response()->json(['code' => 404]);
        }
    }

    public function decryptData(Request $request)
    {
        try {
            $decrypted = Crypt::decryptString($request->token);
            return response()->json(['code' => 200, 'info' => $decrypted]);
        } catch (DecryptException $e) {
            return response()->json(['code' => 404]);
        }
    }
}