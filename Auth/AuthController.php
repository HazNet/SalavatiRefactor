<?php

namespace App\Http\Controllers\User\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UsersOtp;
use App\Rules\Persian;
use App\Rules\Phone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     *
     * @param  Request $request
     * @return bool
     */
    public function checkBeforeAuth(Request $request)
    {
//        User::query()->where('phone', $request->phone)->firstOrFailUser();

        return (bool) User::query()->where('phone', $request->phone)->firstOrFailUser();
    }

    /**
     * Login.
     *
     * @param  Request $request
     * @return mixed
     */
    public function login(Request $request)
    {
        // TODO ADD FORM REQUEST
        $request->validate(['phone' => ['required', new Phone, 'string', 'digits:11']]);

        if (! $this->checkBeforeAuth($request)) {
            return OtpController::sendCode($request);
        }

        if (empty($request->password)) {
            return response()->json(['status' => 'warning', 'message' => 'پسورد خود را وارد کنید']);
        }

        $request->validate(['password' => 'required|string']);
        $credentials = $request->only('phone', 'password');

        $token = Auth::attempt($credentials);
        if (!$token) {
            // TODO::block request
            return response()->json(['status' => 'error', 'message' => 'پسورد اشتباه است',], 401);
        }

        $user = Auth::user();
        return response()->json(['status' => 'logined', 'message' => 'با موفقیت وارد شدید.', 'user' => ['name' => $user->name, 'family' => $user->family, 'phone' => $user->phone, 'auth' => $user->auth, 'code_meli' => $user->code_meli], 'authorisation' => ['token' => $token, 'type' => 'bearer',]], 200);
    }



    public function registerComplete(Request $request)
    {
        $request->validate(['name' =>
            ['required', 'min:2', 'max:32', new Persian()],
            'family' => ['required', 'min:2', 'max:32', new Persian()],
            'phone' => ['required', new Phone()],
            'token' => ['required', 'min:32', 'max:32'],
            'code' => ['required', 'digits:5', 'integer']]);

        $checkCode = OtpController::checkCodeIsTrue($request, false);
        if ($checkCode['status'] !== true) {
            return response()->json(['status' => 'success', 'message' => 'مشکلی پیش آمده',], 406);
        }

        UsersOtp::where('token', $request->token)->delete();
        $data = [
            'name' => $request->name, 'family' => $request->family, 'phone' => $checkCode['res']['phone'],
            'password' => Hash::make($request->password)
        ];

        if (isset($request->inviterId) and is_numeric($request->inviterId)) {
            $data = array_merge($data, ['inviter_id' => $request->inviterId]);
        }

        $user = User::create($data);
        $token = Auth::login($user);

        return response()->json([
            'status' => 'success',
            'message' => 'با موفقیت ثبت نام کردید',
            'user' => ['name' => $user->name, 'family' => $user->family, 'phone' => $user->phone, 'auth' => 'level-1', 'code_meli' => $user->code_meli],
            'authorisation' => ['token' => $token, 'type' => 'bearer',]]);
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


    function resetPassword(Request $request)
    {
        $checkCodeIsTrue = OtpController::checkCodeIsTrue($request);
        if ($checkCodeIsTrue['status'] !== true || strlen($request->password) < 6) {
            return response()->json(['status' => 'failed', 'message' => 'مشکلی پیش آمده !'], 406);
        }

        User::where('phone', $checkCodeIsTrue['res']['phone'])
            ->update(['password' => Hash::make($request->password)]);

        UsersOtp::where('token', $request->token)->delete();

        return response()->json(['status' => 'success', 'message' => 'رمز عبور با موفقیت تغییر یافت.']);
    }

}
