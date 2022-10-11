### سناریو کد

یک تیبل otp و یک تیبل user ساختیم .
تیبل otp , حاوی فیلد های زیر هست

- token = توکن 32 کاراکتری برای هر شماره
- phone = شماره تلفن کابر
- retry = تعداد دفعه های تلاش
- code = کد احراز هویت
- expire_at = زمان منقضی شدن کد (روی دو دقیقه)هست
- created_at = زمان ساخته شدن فرم (از طریق مدل هر 25 دقیقه فیلد ها پاک میشن
- updated_at
- در نرم افزار برای اینکه کاربر لاگین بشه ابتدا متد login صدا زده میشه و اگر کاربر وجود داشت درخواست پسورد میکنه و اگر کاربر وجود نداشت کد احراز هویت میفرسته تاثبت نام کنه
برای فراموشی رمز عبور دقیقا فرایند صحت سنجی که در ثبت نام پیاده سازی شده , به کار گرفتم = متد checkCodeIsTrue

_api.php_
{
- Route::post('login', 'login');
- Route::post('sendCode', 'sendCode');
- Route::post('check_code', 'checkCode');
- Route::post('register_complete', 'registerComplete');
- Route::post('register', 'register');
- Route::post('logout', 'logout');
- Route::post('refresh', 'refresh');
- Route::post('resetPassword', 'resetPassword');
}
