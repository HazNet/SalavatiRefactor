<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Models\UsersOtp;
use App\Rules\Persian;
use App\Rules\Phone;
use Carbon\Carbon;
use Dotenv\Validator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function __construct()
    {
        #$this->middleware('auth:api', ['except' => ['login', 'sendCode', 'register', 'checkCode', 'registerComplete', 'resetPassword']]);
    }

    public function checkBeforeAuth($request)
    {
        $user = User::where('phone', $request->phone)->get();

        if (empty($user[0])) {
            return false;
        }

        return true;
    }

    public function login(Request $request)
    {
        $request->validate(['phone' => ['required', new Phone],]);

        if (!self::checkBeforeAuth($request)) {
            return self::sendCode($request);
        }

        if (empty($request->password)) {
            return response()->json(['status' => 'warning', 'message' => 'پسورد خود را وارد کنید']);
        }

        $request->validate(['password' => 'required|string',]);
        $credentials = $request->only('phone', 'password');

        $token = Auth::attempt($credentials);
        if (!$token) {
            // TODO::block request
            return response()->json(['status' => 'error', 'message' => 'پسورد اشتباه است',], 401);
        }

        $user = Auth::user();
        return response()->json(['status' => 'logined', 'message' => 'با موفقیت وارد شدید.', 'user' => ['name' => $user->name, 'family' => $user->family, 'phone' => $user->phone, 'auth' => $user->auth, 'code_meli' => $user->code_meli], 'authorisation' => ['token' => $token, 'type' => 'bearer',]], 200);
    }

    public function sendCode(Request $request, $phone = false)
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

    public function registerComplete(Request $request)
    {
        $request->validate(['name' => ['required', 'min:2', 'max:32', new Persian()], 'family' => ['required', 'min:2', 'max:32', new Persian()], 'phone' => ['required', new Phone()], 'token' => ['required', 'min:32', 'max:32'], 'code' => ['required', 'digits:5', 'integer']]);

        $checkCode = self::checkCodeIsTrue($request, false);
        if ($checkCode['status'] !== true) {
            return response()->json(['status' => 'success', 'message' => 'مشکلی پیش آمده',], 406);
        }

        UsersOtp::where('token', $request->token)->delete();

        $data = ['name' => $request->name, 'family' => $request->family, 'phone' => $checkCode['res']['phone'], 'password' => Hash::make($request->password)];

        if (isset($request->inviterId) and is_numeric($request->inviterId)) {
            $data = array_merge($data, ['inviter_id' => $request->inviterId]);
        }

        $user = User::create($data);
        $token = Auth::login($user);

        return response()->json(['status' => 'success', 'message' => 'با موفقیت ثبت نام کردید', 'user' => ['name' => $user->name, 'family' => $user->family, 'phone' => $user->phone, 'auth' => 'level-1', 'code_meli' => $user->code_meli], 'authorisation' => ['token' => $token, 'type' => 'bearer',]]);
    }

    public function logout()
    {
        Auth::logout();
        return response()->json(['status' => 'success', 'message' => 'Successfully logged out',]);
    }

    public function refresh()
    {
        return response()->json(['status' => 'success', 'user' => Auth::user(), 'authorisation' => ['token' => Auth::refresh(), 'type' => 'bearer',]]);
    }

    function checkCodeIsTrue(Request $request, $expireCheck = true)
    {
        $request->validate(['token' => 'required|min:32|max:32', 'code' => 'required|digits:5|integer']);
        $userOtp = UsersOtp::firstWhere('token', $request->token);

        if (empty($userOtp)) {
            return ['status' => false, 'res' => ['status' => 'failed', 'message' => 'زمان وارد کردن کد پایان یافته لطفا کد جدیدی دریافت کنید']];
        }

        if ($userOtp->code != $request->code) {
            $userOtp->retry = ($userOtp->retry + 1);
            $userOtp->save();
            return ['status' => false, 'res' => ['status' => 'failed', 'message' => 'کد وارد شده اشتباه است']];
        }

        if ($expireCheck == true and $userOtp->expire_at < time()) {
            return ['status' => false, 'res' => ['status' => 'failed', 'message' => 'زمان وارد کردن کد پایان یافته لطفا کد جدیدی دریافت کنید']];
        }

        if ($userOtp->retry > 5) {
            return ['status' => false, 'res' => ['status' => 'failed', 'message' => 'تعداد تلاش های شما بیشتر از حد مجاز است', 'error' => 'TRY_FLOOD']];
        }


        return ['status' => true, 'res' => ['status' => 'success', 'message' => 'کد صحیح بود', 'expire_auth_form' => Carbon::createFromTimeString($userOtp->updated_at)->addMinutes(25)->timestamp, 'phone' => $userOtp->phone]];
    }

    function checkCode(Request $request)
    {
        $checkCodeIsTrue = self::checkCodeIsTrue($request);
        if ($checkCodeIsTrue['status'] !== true) {
            return response()->json($checkCodeIsTrue['res'], 406);
        }

        return response()->json($checkCodeIsTrue['res']);
    }


    function checkCodeIsSendable($phone)
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


    function resetPassword(Request $request)
    {
        $checkCodeIsTrue = self::checkCodeIsTrue($request);
        if ($checkCodeIsTrue['status'] !== true || strlen($request->password) < 6) {
            return response()->json(['status' => 'failed', 'message' => 'مشکلی پیش آمده !'], 406);
        }

        User::where('phone', $checkCodeIsTrue['res']['phone'])
            ->update(['password' => Hash::make($request->password)]);

        UsersOtp::where('token', $request->token)->delete();

        return response()->json(['status' => 'success', 'message' => 'رمز عبور با موفقیت تغییر یافت.']);
    }

}
