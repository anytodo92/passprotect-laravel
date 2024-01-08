<?php

namespace App\Http\Controllers;

use App\Models\FileListUser;
use App\Models\PaidMembership;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Stripe\StripeClient;
use Illuminate\Support\Facades\File;

class UserController extends Controller
{
    public function createStorageLink() {
        File::link(
            storage_path('app/public'), public_path('storage')
        );
    }

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
            'logo' => $user->logo,
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

        $paymentMode = intval($request->input('paymentMode', config('constants.payment_mode.balance')));
        if ($paymentMode === config('constants.payment_mode.stripe')) {
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
                    'message' => ' Please charge' . $userId
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

        $stripeId = trim($request->input('stripeId'));
        $subscriptionId = trim($request->input('subscriptionId'));
        $userId = intval($request->input('userId'));

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
        //currently it is not need.
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
        //currently it is not need.

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

    public function uploadLogo(Request $request): JsonResponse {
        $file = $request->file('logo');

        if (!$file->isValid()) {
            return response()->json([
                'success' => false,
                'message' => 'Please choose a valid file'
            ]);
        }

        $isPassdropitRequest = $request->is('api/'.config('app.api-version').'/passdropit/*');
        $ret = $file->store('public/uploads'.($isPassdropitRequest ? '/passdropit' : '/notions11'));
        if ($ret === FALSE) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save to storage'
            ]);
        }

        $path = str_replace('public/', '', $ret);
        $user = User::where('id', auth('sanctum')->user()->id)->first();
        $user->logo = $path;
        if (!$user->save()) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save a file path'
            ]);
        }

        return response()->json([
            'success' => true,
            'file' => $path
        ]);
    }

    public function deleteLogo(): JsonResponse {
        $userId = auth('sanctum')->user()->id;
        $user = User::where('id', $userId)->first();

        $ret = Storage::delete('/public/'.$user->logo);
        $user->logo = '';
        $bl = $user->save();
        if (!$bl) {
            return response()->json([
                'success' => false,
                'message' => 'Delete is failed'
            ]);
        }
        return response()->json([
            'success' => $ret,
        ]);
    }

    /*public function capturehook(){
        Stripe::setApiKey("sk_test_51M5Q13E4EcK5n9JaUhs9QmyWqzNl8jy5QhM8cQxORU0RLMk0VtTrTIKSnpDnPLytssrRfqw84Coiq7ymi4ISTyXV00OCRcG1R8");

        $webhookContent = "";
        $webhook = fopen('php://input' , 'rb');
        while (!feof($webhook)) {
            $webhookContent .= fread($webhook, 4096);
        }
        fclose($webhook);
        //decode JSON
        $dat = json_decode($webhookContent,true);

        $query = $this->db->get_where('users', array('stripe_id' => $dat['data']['object']['customer']));
        if($query->num_rows() > 0){ // if user exist
            $user = $query->row();
            $user = $user->user_id;


            if($dat['type'] == 'customer.subscription.deleted'){ // Event Sunscription Deleted
                $query = $this->db->query("update users set is_pro = 0, stripe_id = null, subscription_id = null where user_id ='".$user."'"); // Removes subscription settings
                if($query){
                    $query = $this->db->query("delete from user_settings where id = '".$user."'"); // Downgrade to normal user
                    $query = $this->db->query("update file_list_user set expires_on = null, track_ip = 0, email_notify = 0, expire_count = 0 where user_id ='".$user."'"); // clear Pro settings
                }
            }// End of Event Sunscription Deleted

        }// End of if user exist


        exit(header("Status: 200 OK"));
    }*/
}
