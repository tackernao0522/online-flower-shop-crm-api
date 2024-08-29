<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRules;
use PHPOpenSourceSaver\JWTAuth\Exceptions\JWTException;

class AuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api', [
            'except' => [
                'login',
                'register',
                'forgotPassword',
                'resetPassword'
            ]
        ]);
    }

    public function login(Request $request)
    {
        $this->validateLogin($request);

        $credentials = $request->only('username', 'password');

        if (!$token = Auth::attempt($credentials)) {
            return $this->respondInvalidCredentials();
        }

        $this->updateLastLoginDate(Auth::user());

        return $this->respondWithToken($token);
    }

    public function register(Request $request)
    {
        $this->validateRegister($request);

        $user = User::create([
            'username' => $request->username,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
        ]);

        $token = Auth::login($user);

        return $this->respondWithToken($token);
    }

    public function logout()
    {
        Auth::guard('api')->logout();
        return response()->json(['message' => 'ログアウトしました。']);
    }

    public function refresh()
    {
        try {
            $token = Auth::guard('api')->refresh();
            return $this->respondWithToken($token);
        } catch (JWTException $e) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }
    }

    public function forgotPassword(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $status = Password::sendResetLink($request->only('email'));

        return $status === Password::RESET_LINK_SENT
            ? response()->json(['status' => __($status)], 200)
            : response()->json(['email' => __($status)], 400);
    }

    public function changePassword(Request $request)
    {
        $this->validateChangePassword($request);

        $user = Auth::user();

        if (!Hash::check($request->current_password, $user->password)) {
            return $this->respondInvalidCredentials('現在のパスワードが正しくありません。');
        }

        $user->password = Hash::make($request->new_password);
        $user->save();

        return response()->json(['message' => 'パスワードが正常に変更されました。']);
    }

    public function resetPassword(Request $request)
    {
        $this->validateResetPassword($request);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password)
                ])->setRememberToken(Str::random(60));

                $user->save();
                event(new PasswordReset($user));
            }
        );

        return $status === Password::PASSWORD_RESET
            ? response()->json(['message' => __($status)], 200)
            : response()->json(['error' => __($status)], 422);
    }

    protected function respondWithToken($token)
    {
        return response()->json([
            'accessToken' => $token,
            'tokenType' => 'bearer',
            'expiresIn' => config('jwt.ttl') * 60
        ]);
    }

    protected function validateLogin(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);
    }

    protected function validateRegister(Request $request)
    {
        $request->validate([
            'username' => 'required|string|max:50|unique:users',
            'email' => 'required|string|email|max:100|unique:users',
            'password' => 'required|string|min:8',
            'role' => 'required|in:ADMIN,MANAGER,STAFF',
        ]);
    }

    protected function validateChangePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required',
            'new_password' => ['required', 'min:8', 'confirmed', 'different:current_password'],
        ]);
    }

    protected function validateResetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => ['required', 'confirmed', PasswordRules::defaults()],
        ]);
    }

    protected function updateLastLoginDate(User $user)
    {
        $user->last_login_date = Carbon::now();
        $user->save();
    }

    protected function respondInvalidCredentials($message = 'ユーザー名またはパスワードが正しくありません。')
    {
        return response()->json([
            'error' => [
                'code' => 'INVALID_CREDENTIALS',
                'message' => $message
            ]
        ], 401);
    }
}
