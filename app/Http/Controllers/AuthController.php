<?php

namespace App\Http\Controllers;

use App\Heplers\SecurePassword;
use App\Models\PasswordResetToken;
use App\Models\User;
use App\Notifications\PasswordReset;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function getResponseUserData($user): array {
        $level = $user->is_pro ? config('constants.user_level.pro') : config('constants.user_level.normal');
        $level = $user->user_email == in_array($user->user_email, config('constants.admin_email')) ? config('constants.user_level.super') : $level;
        $ret = [
            'id' => $user->id,
            'user_name' => $user->user_name,
            'user_email' => $user->user_email,
            'level' => $level,
            'stripe_id' => $user->stripe_id,
            'subscription_id' => $user->subscription_id,
            'paypal_id' => $user->paypal_id,
            'balance' => $user->balance,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];

        return $ret;
    }

    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): JsonResponse {
        if (Auth::guard('sanctum')->user()) {
            return response()->json([
                'message' => 'Already authenticated'
            ]);
        }

        $request->validate([
           'email' => ['required', 'email'],
           'password' => ['required']
        ]);

        $user = User::where('user_email', $request->input('email'))
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Could not find user'
            ], 404);
        }

        $ret = SecurePassword::check($request->input('password'), $user->user_password_hash);
        if (!$ret) {
            return response()->json([
                'success' => false,
                'message' => 'Does not match password',
                'data' => $user->user_password
            ], 503);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $user = $this->getResponseUserData($user);
        return response()->json([
            'success' => true,
            'user' => $user,
            'auth_token' => $token,
            'token_type' => 'bearer'
        ], 200);
    }

    public function register(Request $request): JsonResponse {

        if (Auth::guard('sanctum')->user()) {
            return response()->json([
                'message' => 'Already authenticated'
            ]);
        }

        $request->validate([
            'firstName' => ['required'],
            'lastName' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8']
        ]);

        $firstName = trim($request->input('firstName'));
        $lastName = trim($request->input('lastName'));
        $email = trim($request->input('email'));
        $password = trim($request->input('password'));

        $user = User::where('user_name', '=', $firstName.' '.$lastName)->first();
        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'User name already exist, try another name'
            ]);
        }

        $user = User::where('user_email', '=', $email)->first();
        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'That email is already registered! try another'
            ]);
        }

        $user = User::create([
            'user_name' => $firstName . ' ' . $lastName,
            'user_email' => $email,
            'user_password_hash' => SecurePassword::make($password, PASSWORD_BCRYPT_C),
            'is_pro' => config('constants.user_level.normal'),
            'paypal_id' => 0,
            'stripe_id' => 0,
            'subscription_id' => 0,
            'logo' => '',
            'balance' => 0
        ]);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => ''
            ]);
        }

        //Todo: Email scheduler
        //currently it is not need.

        $token = $user->createToken('auth_token')->plainTextToken;

        $user = $this->getResponseUserData($user);

        return response()->json([
            'success' => true,
            'user' => $user,
            'auth_token' => $token,
            'token_type' => 'bearer'
        ]);
    }

    public function forgotPassword(Request $request): JsonResponse {
        if (Auth::guard('sanctum')->user()) {
            return response()->json([
                'message' => 'Already authenticated'
            ]);
        }

        $request->validate([
            'email' => ['required', 'email']
        ]);

        $email = $request->input('email');
        $user = User::where('user_email', $email)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, no account with that email exists. Please try again...'
            ]);
        }

        $token = urlencode(str_shuffle(time().$email));
        $ret = PasswordResetToken::create([
            'user_id' => $user->id,
            'token_signature' => hash('md5', $token),
            'expires_at' => Carbon::now()->addHours(1)
        ]);

        if (!$ret) {
            return response()->json([
                'success' => false,
                'message' => 'The operation is failed, please try again'
            ]);
        }

        $isPassdropitRequest = $request->is('api/'.config('app.api-version').'/passdropit/*');
        $url = $isPassdropitRequest ? config('constants.site_url.passdropit') : config('constants.site_url.notions11');
        $url = $url.'/reset-password/'.$token;

        $fromAddress = $isPassdropitRequest ? env('MAIL_FROM_PASSDROPIT_ADDRESS') : env('MAIL_FROM_NOTIONS11_ADDRESS');
        $fromName = $isPassdropitRequest ? 'Passdropit Support' : 'Notions11 Support';
        $user->notify(new PasswordReset($fromAddress, $fromName, $url));

        return response()->json([
            'success' => true,
//            'url' => $url,
            'message' => 'Success! Please check your email for a password reset link.'
        ]);
    }

    public function resetPassword(Request $request): JsonResponse {
        if (Auth::guard('sanctum')->user()) {
            return response()->json([
                'message' => 'Already authenticated'
            ]);
        }

        $request->validate([
            'token' => ['required'],
            'newPassword' => ['required', 'min:8']
        ]);

        $token = trim($request->input('token'));
        $newPassword = trim($request->input('newPassword'));

        $tokenData = PasswordResetToken::where('token_signature', hash('md5', $token))->first();
        if (!$tokenData) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token for resetting password'
            ]);
        }

        $user = User::where('id', $tokenData->user_id)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Token does not correspond to any existing user'
            ]);
        }

        if (Carbon::now()->greaterThan($tokenData->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'The reset password token has expired'
            ]);
        }

        $user->user_password_hash = SecurePassword::make($newPassword, PASSWORD_BCRYPT_C);
        if (!$user->save()) {
            return response()->json([
                'success' => false,
                'message' => 'The operation is failed'
            ]);
        }

        $tokenData->expires_at = Carbon::now();
        $tokenData->save();

        return response()->json([
            'success' => true
        ]);
    }

    public function changePassword(Request $request): JsonResponse {
        $request->validate([
            'currentPassword' => ['required'],
            'newPassword' => ['required', 'min:8']
        ]);

        $user = Auth::guard('sanctum')->user();
        $currentPassword = trim($request->input('currentPassword'));
        $newPassword = trim($request->input('newPassword'));

        if (!SecurePassword::check($currentPassword, $user->user_password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter correct current password'
            ]);
        }

        $user->user_password_hash = SecurePassword::make($newPassword, PASSWORD_BCRYPT_C);
        if (!$user->save()) {
            return response()->json([
                'success' => false,
                'message' => 'The operation is failed'
            ]);
        }

        return response()->json([
            'success' => true
        ]);
    }

    public function logout() {
        auth()->user()->tokens()->delete();
        return resonse()->json([
            'success' => true
        ]);
    }
}
