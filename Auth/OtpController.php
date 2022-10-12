<?php
namespace App\Http\Controllers\User\Auth;

use App\Models\UsersOtp;
use App\Rules\Phone;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class OtpController extends \App\Http\Controllers\Controller
{
    /**
     * Check code is true.
     *
     * @param  Request $request
     * @param  bool $expireCheck
     * @return array
     */
    public static function checkCodeIsTrue(Request $request, bool $expireCheck = true)
        {
            $request->validate([
                'token' => 'required|min:32|max:32',
                'code' => 'required|digits:5|integer'
            ]);

            $userOtp = UsersOtp::firstWhere('token', $request->token);

            if (empty($userOtp)) {
                return [
                    'status' => false,
                    'res' => [
                        'status' => 'failed',
                        'message' => 'زمان وارد کردن کد پایان یافته لطفا کد جدیدی دریافت کنید'
                    ]
                ];
            }
            if ($userOtp->code !== $request->code) {
                ++$userOtp->retry;
                $userOtp->save();

                return ['status' => false, 'res' => ['status' => 'failed', 'message' => 'کد وارد شده اشتباه است']];
            }
            if ($expireCheck and $userOtp->expire_at < time()) {
                return ['status' => false, 'res' => ['status' => 'failed', 'message' => 'زمان وارد کردن کد پایان یافته لطفا کد جدیدی دریافت کنید']];
            }
            if ($userOtp->retry > 5) {
                return ['status' => false, 'res' => ['status' => 'failed', 'message' => 'تعداد تلاش های شما بیشتر از حد مجاز است', 'error' => 'TRY_FLOOD']];
            }

            return [
                'status' => true,
                'res' => [
                    'status' => 'success',
                    'message' => 'کد صحیح بود',
                    'expire_auth_form' => Carbon::createFromTimeString($userOtp->updated_at)->addMinutes(25)->timestamp,
                    'phone' => $userOtp->phone
                ]
            ];
        }

    public static function checkCode(Request $request)
        {
            $checkCodeIsTrue = self::checkCodeIsTrue($request);
            if ($checkCodeIsTrue['status'] !== true) {
                return response()->json($checkCodeIsTrue['res'], 406);
            }

            return response()->json($checkCodeIsTrue['res']);
        }


    public static function checkCodeIsSendable($phone)
        {
            $userOtp = UsersOtp::firstWhere('phone', $phone);

            if (!isset($userOtp)) {
                return ['create_new_otp'];
            }

            if ($userOtp->expire_at < time()) {
                return ['update_otp', $userOtp];
            }

            if ($userOtp->retry < time()) {
                return ['no_change_code', $userOtp];
            }

            return ['not_send'];
        }


    public static function sendCode(Request $request, $phone = false)
    {
        $phone = $request->phone ?? $phone;
        $validator = \Validator::make(['phone' => $phone], ['phone' => ['required', new Phone()]]);
        if ($validator->fails()) {
            return response()->json(['status' => 'failed', 'message' => $validator->errors()->first()], 406);
        }

        $sendable = self::checkCodeIsSendable($phone);

        if ($sendable == 'not_send') {
            return response()->json(['status' => 'failed', 'message' => 'به دلیل تلاش بیش از حد مجاز امکان ارسال کد وجود ندارد لطفا ساعاتی دیگر امتحان کنید'], 406);
        }

        $token = Str::random(32);
        $expire = Carbon::now()->addMinutes(2)->timestamp;
        $code = rand(10000, 99999);

        if ($sendable[0] == 'create_new_otp') {
            UsersOtp::create(['token' => $token, 'phone' => $phone, 'code' => $code, 'expire_at' => $expire,]);
        } elseif ($sendable[0] == 'no_change_code') {
            $token = $sendable[1]->token;
            $expire = $sendable[1]->expire_at;
        } else {
            $token = $sendable[1]->token;
            $userOtp = UsersOtp::where('phone', $phone)->first();
            $userOtp->code = $code;
            $userOtp->expire_at = $expire;
            $userOtp->retry = 0;
            $userOtp->save();
        }

        return response()->json(['status' => 'success', 'message' => 'کد اعتبارسنجی به شماره تلفن شما ارسال شد.', 'token' => $token, 'timer' => $expire, 'phone' => $phone]);
    }


    }
