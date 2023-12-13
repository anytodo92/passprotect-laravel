<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use \XLSXWriter;
class AdminController extends Controller
{
    public function updatePaypal(Request $request): JsonResponse {
        if (!in_array(auth('sanctum')->user()->user_email, config('constants.admin_email'))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a permission'
            ]);
        }

        $request->validate([
            'paypalEmail' => 'required|email'
        ]);

        $user = User::where('id', auth('sanctum')->user()->id)->first();
        $user->paypal_id = trim($request->input('paypalEmail'));
        if ($user->save()) {
            return response()->json([
                'success' => true
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'The operation is failed'
            ]);
        }
    }

    public function getEarningLinkList(Request $request): JsonResponse {
        if (!in_array(auth('sanctum')->user()->user_email, config('constants.admin_email'))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a permission'
            ]);
        }

        $request->validate([
            'period' => 'required',
            'userId' => 'required'
        ]);

        $period = $request->input('period');
        $userId = $request->input('userId');

        if ($period === '1') {
            $ret = DB::table('')
                ->fromSub(function ($query) {
                    $query->select(
                        DB::raw('count(link_id) as count'), 'link_id', 'amount as price', DB::raw('CAST(SUM(amount) as signed) as total'), 'status'
                    )
                        ->from('paid_links')
                        ->groupBy(['status', 'link_id', 'amount'])
                        ->orderBy('created_at', 'desc');
                }, 'a')
                ->join(DB::raw('file_list_user b'), 'a.link_id', '=', 'b.id')
                ->select('a.link_id', 'a.status', 'a.price', 'a.total', 'a.count', 'b.passdrop_url')
                ->where('b.user_id', $userId)
                ->where('a.status', config('constants.payment_status.process'))
                ->get();
        } else if ($period === '2') {
            $ret = DB::table('')
                ->fromSub(function ($query) {
                    $query->select(
                        DB::raw('count(link_id) as count'), 'link_id', 'amount as price', DB::raw('CAST(SUM(amount) as signed) as total'), 'status'
                    )
                        ->from('paid_links')
                        ->groupBy(['status', 'link_id', 'amount'])
                        ->orderBy('created_at', 'desc');
                }, 'a')
                ->join(DB::raw('file_list_user b'), 'a.link_id', '=', 'b.id')
                ->select('a.link_id', 'a.status', 'a.price', 'a.total', 'a.count', 'b.passdrop_url')
                ->where('b.user_id', $userId)
                ->get();
        } else {
            $ret = DB::table('')
                ->fromSub(function ($query) use ($period) {
                    $query->select(
                        DB::raw('count(link_id) as count'), 'link_id', 'amount as price', DB::raw('CAST(SUM(amount) as signed) as total'), 'status'
                    )
                        ->from('paid_links')
                        ->where(DB::raw('DATE_FORMAT(created_at, \'%Y-%m\')'), '=', $period)
                        ->groupBy(['status', 'link_id', 'amount'])
                        ->orderBy('created_at', 'desc');
                }, 'a')
                ->join(DB::raw('file_list_user b'), 'a.link_id', '=', 'b.id')
                ->select('a.link_id', 'a.status', 'a.price', 'a.total', 'a.count', 'b.passdrop_url')
                ->where('b.user_id', $userId)
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $ret
        ]);
    }

    public function getUserList(): JsonResponse {
        if (!in_array(auth('sanctum')->user()->user_email, config('constants.admin_email'))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a permission'
            ]);
        }

        $userList = User::where('id', '<>', auth('sanctum')->user()->id)
            ->select('id', 'user_email', 'user_name', 'balance')
            ->where('is_pro', config('constants.user_level.pro'))
            ->get();
        return response()->json([
            'success' => true,
            'data' => $userList
        ]);
    }

    public function exportActivity(Request $request): JsonResponse {
        if (!in_array(auth('sanctum')->user()->user_email, config('constants.admin_email'))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a permission'
            ]);
        }

        $isPassdropitRequest = $request->is('api/'.config('app.api-version').'/passdropit/*');

        $fileLists = DB::table('file_list_user')
            ->select(DB::raw('count(id) as link_count'), DB::raw('sum(download_count) as download_count'), 'user_id')
            ->groupBy('user_id');

        $userList = DB::table('users', 'a')
            ->leftJoinSub($fileLists, 'b', 'a.id', '=', 'b.user_id')
            ->select('a.user_name', 'a.user_email', 'a.is_pro', 'a.id', 'b.link_count', 'b.download_count')
            ->get();
        $userList = $userList->map(function ($data) {
            return json_decode(json_encode($data), true);
        });

        $userDataHeader = array(
            'User name' => 'string',
            'User email' => 'string',
            'Is Pro' => 'string',
            'User ID' => 'string',
            'Count of Links' => 'string',
            'Count of Downloads' => 'string'
        );


        $linkList = DB::table('file_list_user')
            ->select('user_id', 'dropbox_url', 'passdrop_url', 'link_type')
            ->orderBy('user_id')
            ->orderBy('link_type')
            ->get();

        $linkList = $linkList->map(function ($data) {
            return json_decode(json_encode($data), true);;
        });

        $linkHeader = $isPassdropitRequest ? [
            'User ID' => 'string',
            'Dropbox Url' => 'string',
            'Passdop Url' => 'string',
            'Link Type' => 'string'
        ] : [
            'User ID' => 'string',
            'Notion Url' => 'string',
            'Passdop Url' => 'string',
            'Link Type' => 'string'
        ];

        $writer = new XLSXWriter;
        $writer->setAuthor('Passdropit System');
        $writer->writeSheet($userList->toArray(),'User Details',$userDataHeader);
        $writer->writeSheet($linkList->toArray(),'User Links',$linkHeader);

        $path = public_path('/export/UserDataExport.xlsx');
        $writer->writeToFile($path);

        return response()->json([
            'success' => true,
            'path' => '/export/UserDataExport.xlsx'
        ]);
    }
}
