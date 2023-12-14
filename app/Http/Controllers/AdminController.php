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

    public function linkReport(Request $request) {
        if (!in_array(auth('sanctum')->user()->user_email, config('constants.admin_email'))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a permission'
            ]);
        }

        $request->validate([
            'period' => 'required'
        ]);

        $period = $request->input('period');
        $userName = trim($request->input('userName'));
        $url = trim($request->input('url'));

        $day = 1;
        if ($period === '1') {
            $day = 1;
        } else if ($period === '2') {
            $day = 7;
        } else if ($period === '3') {
            $day = 30;
        }

        $builder = DB::table('')
            ->fromSub(function ($query) use ($day) {
                return $query->from('daily_downloads')
                    ->where('download_date', '>=', DB::raw('(curdate() - INTERVAL '.$day.' DAY)'))
                    ->select('link_id', 'passdrop_url', DB::raw('CAST(SUM(IFNULL(downloads, 0)) as signed) as period_download_count'))
                    ->groupBy(['link_id', 'passdrop_url']);
            }, 'a')
            ->join(DB::raw('file_list_user as b'), 'a.link_id', '=', 'b.id')
            ->leftJoin(DB::raw('users as c'), 'b.user_id', '=', 'c.id');

        if (!empty($url)) {
            $builder = $builder->where('b.passdrop_url', $url);
        }
        if (!empty($userName)) {
            $builder = $builder->where('c.user_name', $userName);
        }

        $result = $builder->select('c.id', 'b.passdrop_url', DB::raw('IFNULL(b.download_count, 0) as total_download_count'),
                'a.period_download_count', DB::raw('b.expires_on as expiry_on'), DB::raw('b.expire_count as expiry_count'),
                'c.user_name', 'c.user_email', 'c.is_pro')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }

    public function userAnalytics(Request $request): JsonResponse {
        if (!in_array(auth('sanctum')->user()->user_email, config('constants.admin_email'))) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have a permission'
            ]);
        }

        $userName = trim($request->input('userName'));

        $builder = DB::table('users', 'a')
            ->leftJoinSub(function ($join) {
                return $join->from('file_list_user')
                    ->whereNotNull('user_id')
                    ->select('user_id', DB::raw('SUM(1) as link_count'), DB::raw('CAST(SUM(IFNULL(download_count, 0)) as signed) as download_count'))
                    ->groupBy('user_id');
            }, 'b', 'a.id', '=', 'b.user_id');

        if (!empty($userName)) {
            $builder = $builder->where('a.user_name', '=', $userName);
        }

        $result = $builder->select('a.id', DB::raw('IFNULL(b.link_count, 0) as link_count'),
            DB::raw('IFNULL(b.download_count, 0) as download_count'), 'a.user_name', 'a.user_email',
            'a.stripe_id', 'a.subscription_id', 'a.logo', 'a.is_pro')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $result
        ]);
    }
}
