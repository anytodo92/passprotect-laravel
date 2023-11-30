<?php

namespace App\Http\Controllers;

use App\Heplers\SecurePassword;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
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

        $level = $user->is_pro ? 1 : 0;
        $level = $user->user_email == 'shawn-passdropit@shawntaylorphoto.com'
            || $user->user_email == 'robinkuipers@hotmail.com' ? 2 : $level;
        $user = [
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

        $firstName = $request->string('firstName')->trim();
        $lastName = $request->string('lastName')->trim();
        $email = $request->string('email')->trim();
        $password = $request->string('password')->trim()->value();

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
            'is_pro' => config('constants.user-level.normal'),
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

        $token = $user->createToken('auth_token')->plainTextToken;

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
            ], 201);
        }

        $request->validate([
            'email' => ['required', 'email']
        ]);

        $email = $request->string('email');
        $user = User::where('user_email', '=', $email)->get();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry, no account with that email exists. Please try again...'
            ]);
        }

        $url = env('FRONTEND_SITE_URL').'/reset-password/'.Hash::make();
        //Todo: Sending email

        return response()->json([]);
    }

    public function resetPassword(Request $request): JsonResponse {
        if (Auth::guard('sanctum')->user()) {
            return response()->json([
                'message' => 'Already authenticated'
            ], 201);
        }

        $request->validate([
            'token' => ['required'],
            'new_password' => ['required', 'min:8']
        ]);

        //Todo: Implement reset password feature;

        return response()->json([]);
    }

    public function changePassword(Request $request): JsonResponse {
        $request->validate([
            'old_password' => ['required'],
            'new_password' => ['required', 'min:8']
        ]);

        $user = Auth::guard('sanctum')->user();
        $oldPassword = $request->string('old_password')->trim()->value();
        $newPassword = $request->string('new_password')->trim()->value();

        if (!SecurePassword::check($oldPassword, $user->user_password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Please enter correct old password'
            ]);
        }

        $user->user_password_hash = SecurePassword::make($newPassword, PASSWORD_BCRYPT_C);
        if (!$user->save()) {
            return response()->json([
                'success' => false,
                'message' => 'Operation is failed'
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
