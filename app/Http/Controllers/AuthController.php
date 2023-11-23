<?php

namespace App\Http\Controllers;

use App\Heplers\SecurePassword;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    /**
     * Handle an authentication attempt.
     */
    public function login(Request $request): JsonResponse {
        if ($request->user('sanctum')) {
            return response()->json([
                'message' => 'Already authenticated'
            ], 201);
        }

        $request->validate([
           'email' => ['required', 'email'],
           'password' => ['required']
        ]);

        $user = User::where('user_email', $request->input('email'))
            ->firstOrFail();
        if (!$user) {
            return response()->json([
                'message' => 'Could not find user'
            ], 503);
        }

        $ret = SecurePassword::check($request->input('password'), $user->user_password_hash);
        if (!$ret) {
            return response()->json([
                'message' => 'Does not match password',
                'data' => $user->user_password
            ], 503);
        }

        $token = $user->createToken('auth_token')->plainTextToken;
        return response()->json([
            'user' => $user,
            'auth_token' => $token,
            'token_type' => 'bearer'
        ], 200);
    }

    public function register(Request $request): JsonResponse {
        if ($request->user('sanctum')) {
            return response()->json([
                'message' => 'Already authenticated'
            ], 201);
        }

        $request->validate([
            'firstname' => ['required'],
            'lastname' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8']
        ]);

        $firstName = $request->string('firstname')->trim();
        $lastName = $request->string('lastname')->trim();
        $email = $request->string('email')->trim();
        $password = $request->string('password')->trim()->value();

        $user = User::where('user_name', '=', $firstName.' '.$lastName)->get();
        if ($user) {
            return response()->json([
                'success' => false,
                'message' => 'User name already exist, try another name'
            ]);
        }

        $user = User::where('user_email', '=', $email)->get();
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

    public function resetPassword(Request $request): JsonResponse {
        return response()->json([]);
    }

    public function forgotPassword(Request $request): JsonResponse {
        return response()->json([]);
    }


}
