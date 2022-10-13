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
     * @throws \Exception
     */
    public function login(Request $request)
    {
        // TODO ADD FORM REQUEST
        $request->validate([
            'phone'    => ['required', new Phone, 'string', 'digits:11'],
            'password' => ['required', 'string', 'min:3', 'max:255'],
        ]);

        if (! $this->checkBeforeAuth($request)) {
            return OtpController::sendCode($request);
        }

        if (! $token = $this->attemptAuth($request->only('phone', 'password'))) {
            // TODO::block request
            return response()->json(['status' => 'error', 'message' => 'پسورد اشتباه است'], 401);
        }

        $user = Auth::user();

        return response()->json([
            'status'  => 'login',
            'message' => 'با موفقیت وارد شدید.',
            'user'    => [
                'name'      => $user->name,
                'family'    => $user->family,
                'phone'     => $user->phone,
                'auth'      => $user->auth,
                'code_meli' => $user->code_meli
            ],
            'authorisation' => ['token' => $token, 'type' => 'bearer']
        ], 200);
    }

    /**
     * Complete register.
     *
     * @param  Request $request
     * @return mixed
     */
    public function registerComplete(Request $request)
    {
        $request->validate([ // TODO FORM REQUEST
            'name' => ['required', 'min:2', 'max:32', new Persian()],
            'family' => ['required', 'min:2', 'max:32', new Persian()],
            'phone' => ['required', new Phone()],
            'token' => ['required', 'min:32', 'max:32'],
            'code' => ['required', 'digits:5', 'integer']
        ]);

        $checkCode = OtpController::checkCodeIsTrue($request, false);

        if ($checkCode['status'] !== true) {
            return response()->json(['status' => 'error', 'message' => 'مشکلی پیش آمده'], 406);
        }

        $this->deleteOtp($request);

//        if (isset($request->inviterId) and is_numeric($request->inviterId)) {
//            $data = array_merge($data, ['inviter_id' => $request->inviterId]);
//        }

        $user = User::query()->create([
            'name'       => $request->name,
            'family'     => $request->family,
            'phone'      => $checkCode['res']['phone'],
            'password'   => Hash::make($request->password),
            'inviter_id' => isset($request->inviterId) and is_numeric($request->inviterId) ? $request->inviterId : null,
        ]);
        $token = Auth::login($user);

        return response()->json([
            'status'  => 'success',
            'message' => 'با موفقیت ثبت نام کردید',
            'user'    => [
                'name' => $user->name,
                'family' => $user->family,
                'phone' => $user->phone,
                'auth' => 'level-1',
                'code_meli' => $user->code_meli
            ],
            'authorisation' => ['token' => $token, 'type' => 'bearer']
        ]);
    }

    /**
     * Logout user.
     *
     * @return mixed
     */
    public function logout()
    {
        Auth::logout();
        return response()->json(['status' => 'success', 'message' => 'Successfully logged out']);
    }

    /**
     * Refresh token.
     *
     * @return mixed
     */
    public function refresh()
    {
        return response()->json([
            'status' => 'success',
            'user'   => Auth::user(),
            'authorisation' => [
                'token' => Auth::refresh(),
                'type' => 'bearer'
            ]
        ]);
    }

    /**
     * Reset password.
     *
     * @param  Request $request
     * @return mixed
     */
    public function resetPassword(Request $request)
    {
        // TODO ADD FORM REQUEST OR VALIDATE IN FUNCTION
        $checkCodeIsTrue = OtpController::checkCodeIsTrue($request);

        if ($checkCodeIsTrue['status'] !== true || strlen($request->password) > 6) { // TODO CHECK OPERATION
            return response()->json(['status' => 'failed', 'message' => 'مشکلی پیش آمده !'], 406);
        }

        User::query()
            ->where('phone', $checkCodeIsTrue['res']['phone'])
            ->update(['password' => Hash::make($request->password)]);

        $this->deleteOtp($request);

        return response()->json(['status' => 'success', 'message' => 'رمز عبور با موفقیت تغییر یافت.']);
    }

    /**
     * Attempt by request.
     *
     * @param  array $data
     * @return mixed
     */
    private function attemptAuth(array $data)
    {
        return Auth::attempt($data);
    }

    /**
     * Delete otp by token.
     *
     * @param  $token
     * @return void
     */
    private function deleteOtp($token): void
    {
        UsersOtp::query()->where('token', $token)->delete();
    }
}
/*
 * Dry => Don't Repeat Yourself
 */
