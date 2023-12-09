<?php

namespace App\Http\Controllers;

use App\Models\FileListUser;
use App\Models\PaidMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class UserController extends Controller
{
    public function getResponseUserData($user): array {
        $level = $user->is_pro ? config('constants.user_level.pro') : config('constants.user_level.normal');
        $level = $user->user_email == in_array($user->user_email, config('constants.admin_email')) ? config('constants.user_level.super') : $level;
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

        return $user;
    }

    public function subscription(Request $request): JsonResponse {
        $isPassdropitRequest = $request->is('api/'.config('app.api-version').'/passdropit/*');
        $stripeId = auth('sanctum')->user()->stripe_id;
        $stripeKey = $isPassdropitRequest ? config('constants.stripe.passdropit_key') : config('constants.stripe.notions11_key');
        $stripe = new StripeClient($stripeKey);

        if (empty($stripeId)) {
            return response()->json([
                'success' => false,
                'message' => 'You have to integrate stripe payment.'
            ]);
        }

        $returnUrl = $isPassdropitRequest ? config('constants.site_url.passdropit') : config('constants.site_url.notions11');

        $session = $stripe->billingPortal->sessions->create([
            'customer' => $stripeId,
            'return_url' => $returnUrl
        ]);

        return response()->json([
            'success' => true,
            'url' => $session->url
        ]);
    }

    public function upgradePro(Request $request): JsonResponse {
        $request->validate([
            'paymentMode' => 'required'
        ]);

        $paymentMode = $request->integer('paymentMode', config('constants.payment_mode.balance'));
        if ($paymentMode === config('constants.payment_mode.credit')) {
            $isPassdropitRequest = $request->is('api/'.config('app.api-version').'/passdropit/*');
            $stripeKey = $isPassdropitRequest ? config('constants.stripe.passdropit_key') : config('constants.stripe.notions11_key');
            $stripe = new StripeClient($stripeKey);

            $userId = auth('sanctum')->user()->id;
            $stripeId = auth('sanctum')->user()->stripe_id;
//        $subscriptionId = auth('sanctum')->user()->subscription_id;

            $returnUrl = $isPassdropitRequest ? config('constants.site_url.passdropit') : config('constants.site_url.notions11');

            if (!empty($stripeId)) {
                $session = $stripe->checkout->sessions->create([
                    'success_url' => $returnUrl.'/user/upgrade-pro/success',
                    'cancel_url' => $returnUrl,
                    'customer' => $stripeId,
                    'line_items' =>[
                        [
                            'price' => 'price_1M5QUkE4EcK5n9JaGcmiQB4F',
                            'quantity' => config('constants.prices.upgrade'),
                        ],
                    ],
                    'mode' => 'subscription',
                    'allow_promotion_codes' => true,
                    'metadata' => [
                        'user_id' => $userId,
                    ],
                ]);
            } else {
                $session = $stripe->checkout->sessions->create([
                    'success_url' => $returnUrl.'/user/upgrade-pro/success',
                    'cancel_url' => $returnUrl,
                    'line_items' =>[
                        [
                            'price' => 'price_1M5QUkE4EcK5n9JaGcmiQB4F',
                            'quantity' => config('constants.prices.upgrade'),
                        ],
                    ],
                    'mode' => 'subscription',
                    'allow_promotion_codes' => true,
                    'metadata' => [
                        'user_id' => $userId,
                    ],
                ]);
            }

            return response()->json([
                'success' => true,
                'url' => $session->url
            ]);
        } else {
            $userId = auth('sanctum')->user()->id;
            $user = User::where('id', $userId)->first();
            if ($user->balance < config('constants.prices.upgrade')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Your balance is not enough. Please charge'
                ]);
            }

            $data = PaidMembership::create([
                'user_id' => $userId,
                'type' => 1,
                'amount' => config('constants.prices.upgrade')
            ]);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'The operation is failed'
                ]);
            }

            $user->is_pro = config('constants.user_level.pro');
            $user->balance = $user->balance - config('constants.prices.upgrade');
            $bl = $user->save();

            if (!$bl) {
                return response()->json([
                    'success' => false,
                    'message' => 'The operation is failed'
                ]);
            }

            $user = $this->getResponseUserData($user);

            return response()->json([
                'success' => true,
                'user' => $user
            ]);
        }
    }

    public function commitPro(Request $request): JsonResponse
    {
//        $request->validate([
//            'stripeId' => 'required',
//            'subscriptionId' => 'required',
//            'userId' => 'required',
//        ]);

        $stripeId = $request->string('stripeId')->value();
        $subscriptionId = $request->string('subscriptionId')->value();
        $userId = $request->integer('userId');

        $user = User::where('id', $userId)->first();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Could not find a user'
            ]);
        }

        $user->is_pro = config('constants.user_level.pro');
        $user->stripe_id = $stripeId;
        $user->subscription_id = $subscriptionId;
        $bl = $user->save();

        if (!$bl) {
            return response()->json([
                'success' => false,
                'message' => 'Upgrade is failed'
            ]);
        }

        $user = $this->getResponseUserData($user);
        return response()->json([
            'success' => true,
            'user' => $user
        ]);

        //Todo: do we have to store flags for email notify, track ip, drop folder in user setting table ?
    }

    public function cancelPro(): JsonResponse {
        $userId = auth('sanctum')->user()->id;
        $user = User::where('id', $userId)->first();
        $user->stripe_id = '';
        $user->subscription_id = '';
        $user->is_pro = config('constants.user_level.normal');
        $bl = $user->save();

        FileListUser::where('user_id', $userId)
            ->update([
                'email_notify' => 0,
                'track_ip' => 0,
                'is_paid' => null,
                'expires_on' => null,
                'expire_count' => 0
            ]);

        //Todo: do we have to store flags for email notify, track ip, drop folder in user setting table ?

        if (!$bl) {
            return response()->json([
                'success' => false,
                'message' => 'The operation is failed'
            ]);
        }

        $user = $this->getResponseUserData($user);

        return response()->json([
            'success' => true,
            'user' => $user
        ]);
    }
}
