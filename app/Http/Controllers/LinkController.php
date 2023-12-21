<?php

namespace App\Http\Controllers;

use App\Models\DailyDownload;
use App\Models\IpTracker;
use App\Models\PaidLink;
use App\Models\User;
use App\Notifications\LinkDownload;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Ixudra\Curl\Facades\Curl;
use App\Models\FileListUser;
use function Monolog\toArray;

class LinkController extends Controller
{
    protected function getResponseLinkInfo($link): array {
        $files = [];
        $arrUrl = explode(',', $link->dropbox_url);
        $arrName = explode(',', $link->filename);
        for ($i = 0; $i < count($arrUrl); $i++) {
            $file = [];
            $file['url'] = $arrUrl[$i];
            if (count($arrName) > $i) {
                $file['name'] = $arrName[$i];
            }

            $files[] = $file;
        }

        $service = 1;
        foreach (config('constants.service_type') as $k => $v) {
            if ($v === $link->service) {
                $service = $k;
                break;
            }
        }

        $linkType = 1;
        foreach (config('constants.link_type') as $k => $v) {
            if ($v === $link->link_type) {
                $linkType = $k;
                break;
            }
        }

        return [
            'id' => $link->id,
            'files' => $files,
            'link' => $link->passdrop_url,
            'password' => $link->passdrop_pwd,
            'emailNotify' => $link->email_notify === 1,
            'trackIp' => $link->track_ip === 1,
            'cost' => !empty($link->cost) ? $link->cost : 0,
            'service' => $service,
            'linkType' => $linkType,
            'expiryCount' => $link->expiry_count,
            'expiryOn' => $link->expiry_on,
            'downloadCount' => $link->download_count,
        ];
    }

    public function checkGoogleLink(Request $request): JsonResponse {
        $request->validate([
            'url' => ['required']
        ]);

        $url = trim($request->input('url'));
        $response = Curl::to($url)
            ->returnResponseObject()
            ->get();

        if ($response->status === 200) {
            return response()->json(['success' => true]);
        } else {
            return response()->json(['success' => false]);
        }
    }

    public function saveLink(Request $request): JsonResponse {
        $request->validate([
            'files' => 'required|array|min:1',
            'service' => 'required',
            'link' => 'required',
            'password' => 'required',
            'linkType' => 'required',
            'emailNotify' => 'required',
            'trackIp' => 'required',
            'cost' => 'required|numeric',
            'expiryCount' => 'required',
        ]);

        $files = $request->input('files');
        $service = intval($request->input('service', 0));
        $link = $request->input('link');
        $password = $request->input('password');
        $linkType = intval($request->input('linkType', 0));
        $blTrackIp = $request->boolean('trackIp', false);
        $blEmailNotify = $request->boolean('emailNotify', false);
        $cost = intval($request->input('cost', 0));
        $expiryCount = intval($request->input('expiryCount', 0));
        $expiryOn = $request->input('expiryOn');

        $isExists = FileListUser::where('passdrop_url', $link)
            ->get()
            ->count();
        if ($isExists) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry that URL is taken, please try another'
            ]);
        }

        $arr = [];
        foreach ($files as $file) {
            $arr[] = $file['url'];
        }
        $url = implode(',', $arr);

        $fileName = '';
        if (!empty($files[0]['name'])) {
            $arr = [];
            foreach ($files as $file) {
                $arr[] = $file['name'];
            }
            $fileName = implode(',', $arr);
        }

        $insertData = [
            'dropbox_url' => $url,
            'passdrop_url' => $link,
            'passdrop_pwd' => $password,
            'service' => config('constants.service_type')[$service],
            'filename' => $fileName,
            'user_id' => auth('sanctum')->user() ? auth('sanctum')->user()->id : 0,
            'is_verified' => config('constants.is_verified'),
            'download_count' => 0,
            'link_type' => config('constants.link_type')[$linkType],
            'email_notify' => $blEmailNotify ? 1 : 0,
            'track_ip' => $blTrackIp ? 1 : 0,
            'expire_count' => $expiryCount,
            'expires_on' => empty($expiryOn) ? null : $expiryOn,
            'is_paid' => empty($cost) ? null : $cost,
            'created_on' => Carbon::today()->format('Y-m-d')
        ];

//        return response()->json([
//            'success' => false,
//            'message' => 'testing',
//            'data' => $insertData
//        ]);

        $ret = FileListUser::create($insertData);

        if ($ret) {
            return response()->json([
                'success' => true,
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'The operation is failed'
            ]);
        }
    }

    public function updateLink(Request $request): JsonResponse {
        $request->validate([
            'id' => 'required',
            'files' => 'required|array|min:1',
//            'service' => 'required',
//            'link' => 'required',
//            'password' => 'required',
//            'linkType' => 'required',
            'emailNotify' => 'required',
            'trackIp' => 'required',
            'cost' => 'required|numeric',
            'expiryCount' => 'required',
        ]);

        $id = intval($request->input('id', 0));
        $files = $request->input('files');
//        $service = intval($request->input('service', 0));
//        $link = $request->input('link');
//        $password = $request->input('password');
//        $linkType = intval($request->input('linkType', 0));
        $blTrackIp = $request->boolean('trackIp', false);
        $blEmailNotify = $request->boolean('emailNotify', false);
        $cost = intval($request->input('cost', 0));
        $expiryCount = intval($request->input('expiryCount', 0));
        $expiryOn = $request->input('expiryOn');

        $arr = [];
        foreach ($files as $file) {
            $arr[] = $file['url'];
        }
        $url = implode(',', $arr);

        $fileName = '';
        if (!empty($files[0]['name'])) {
            $arr = [];
            foreach ($files as $file) {
                $arr[] = $file['name'];
            }
            $fileName = implode(',', $arr);
        }

        $data = FileListUser::where('id', $id)->first();
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Bad request'
            ]);
        }
        $data->dropbox_url = $url;
//        $data->passdrop_url = $link;
//        $data->passdrop_pwd = $password;
//        $data->service = config('constants.service_type')[$service];
        $data->filename = $fileName;
//        $data->user_id = auth('sanctum')->user() ? auth('sanctum')->user()->id : 0;
//        $data->is_verified = $blEmailNotify ? 1 : 0;
//        $data->link_type  = config('constants.link_type')[$linkType];
        $data->email_notify = $blEmailNotify ? 1 : 0;
        $data->track_ip = $blTrackIp ? 1 : 0;
        $data->expires_on = empty($expiryOn) ? null : $expiryOn;
        $data->expire_count = $expiryCount;
        $data->is_paid = $cost;
        $data->save();

        return response()->json([
            'success' => true,
        ]);
    }

    public function getList(): JsonResponse {
        $userId = auth('sanctum')->user()->id;
        $list = FileListUser::where('user_id', $userId)
            ->select('id', 'dropbox_url', 'passdrop_url', 'passdrop_pwd', 'user_id',
                'download_count', 'link_type', 'expires_on as expiry_on', 'expire_count as expiry_count',
                'is_paid as cost', 'service', 'filename', 'track_ip', 'email_notify')
            ->get();

        $list = $list->map(function ($item) {
            return $this->getResponseLinkInfo($item);
        });

        return response()->json($list);
    }

    public function deleteLink($id): JsonResponse {
        $data = FileListUser::where('id', $id)
            ->where('user_id', auth('sanctum')->user()->id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Could not find a link'
            ]);
        }
        $bl = FileListUser::where('id', $id)->delete();
        return response()->json([
            'success' => $bl,
            'message' => $bl ? 'Successfully deleted' : 'Error occurred'
        ]);
    }

    public function analytics(Request $request): JsonResponse {
        $request->validate([
            'linkId' => 'required'
        ]);

        $linkId = $request->input('linkId');
        $list = DB::table('')
            ->fromSub(function ($query) use ($linkId) {
                $query->select(
                    DB::raw('sum(1) as download_count_by_ip'),
                    DB::raw('concat(city,", ",country) as city'),
                    'ip'
                )
                    ->from('ip_tracker')
                    ->where('link_id', $linkId)
                    ->groupBy('country', 'city', 'ip');
            }, 'a')
            ->select(
                DB::raw('sum(1) as ip_cnt'),
                'city',
                DB::raw('sum(a.download_count_by_ip) as download_count_by_city')
            )
            ->groupBy('a.city')
            ->get();

        $list = $list->map(function ($item) {
           return [
               'downloadCount' => intval($item->download_count_by_city),
               'ipCount' => intval($item->ip_cnt),
               'city' => $item->city,
           ];
        });
        return response()->json($list);
    }

    public function getLinkDetail(Request $request): JsonResponse {
        $request->validate([
            'url' => 'required'
        ]);

        $loggedInUser = auth('sanctum')->user();
        $url = trim($request->input('url'));
        $linkInfo = FileListUser::where('passdrop_url', $url)->first();

        if (!$linkInfo) {
            return response()->json([
                'success' => false,
                'message' => 'The link does not exist from db'
            ]);
        }

        if (!empty($linkInfo->user_id)) {
            $owner = User::where('id', $linkInfo->user_id)->first();
        }

        $requirePaid = false;
        if ($linkInfo->is_paid > 0) {
            if ($loggedInUser) {
                if($loggedInUser->id != $linkInfo->user_id) {
                    $paidInfo = PaidLink::where('link_id', $linkInfo->id)
                        ->where('user_id', $loggedInUser->id)
                        ->where('status', config('constants.payment_status.done'))
                        ->first();
                    $requirePaid = !$paidInfo;
                } else {
                    $requirePaid = false;
                }
            } else {
                $requirePaid = true;
            }
        }

        $t = $this->getResponseLinkInfo($linkInfo);

        $ret = [
            'id' => $t['id'],
            'files' => $t['files'],
            'link' => $t['link'],
            'emailNotify' => $t['emailNotify'],
            'trackIp' => $t['trackIp'],
            'cost' => $t['cost'],
            'service' => $t['service'],
            'linkType' => $t['linkType'],
            'expiryCount' => $t['expiryCount'],
            'expiryOn' => $t['expiryOn'],
            'downloadCount' => $t['downloadCount'],
            'ownerName' => !empty($owner) ? $owner->user_name : '',
            'ownerEmail' => !empty($owner) ? $owner->user_email : '',
            'ownerLogo' => !empty($owner) ? $owner->logo : '',
            'requirePaid' => $requirePaid,
        ];

        return response()->json([
            'success' => true,
            'data' => $ret
        ]);
    }

    public function buyLink(Request $request): JsonResponse {
        $request->validate([
            'linkId' => 'required'
        ]);

        $linkId = intval($request->input('linkId'));
        $linkInfo = FileListUser::where('id', $linkId)->first();
        if (!$linkInfo) {
            return response()->json([
                'success' => false,
                'message' => 'The link not exists'
            ]);
        }

        $user = User::where('id', auth('sanctum')->user()->id)->first();
        if ($user->balance < $linkInfo->is_paid) {
            return response()->json([
                'success' => false,
                'message' => 'Your balance is not enough,please charge'
            ]);
        }

        $ret = PaidLink::create([
            'user_id' => $user->id,
            'link_id' => $linkId,
            'amount' => $linkInfo->is_paid,
            'type' => config('constants.payment_mode.balance'),
            'status' => config('constants.payment_status.done')
        ]);

        if (!$ret) {
            return response()->json([
                'success' => false,
                'message' => 'The operation is failed'
            ]);
        }

        $user->balance = $user->balance - $linkInfo->is_paid;
        $user->save();

        $seller = User::where('id', $linkInfo->user_id)->first();
        if (!empty($linkInfo->user_id) && $seller) {
            $seller->balance = $seller->balance + $linkInfo->is_paid;
            $seller->save();
        }

        return response()->json([
            'success' => true
        ]);
    }

    public function validateLink(Request $request) {
        $request->validate([
            'linkId' => 'required',
            'password' => 'required'
        ]);

        $loggedInUser = auth('sanctum')->user();
        $linkId = intval($request->input('linkId'));
        $password = trim($request->input('password'));

        $linkInfo = FileListUser::where('id', $linkId)
            ->where('passdrop_pwd', $password)
            ->first();

        if (!$linkInfo) {
            return response()->json([
                'success' => false,
                'message' => 'The password is invalid'
            ]);
        }

        if ($loggedInUser && $loggedInUser->id === $linkInfo->user_id) {
            return response()->json([
                'success' => true
            ]);
        }

        $linkInfo->download_count = $linkInfo->download_count + 1;
        $linkInfo->last_download = Carbon::now()->format('Y-m-d H:i:s');
        $linkInfo->save();

        $today = Carbon::today()->format('Y-m-d');
        $dailyDownload = DailyDownload::where('download_date', $today)
            ->where('link_id', $linkInfo->id)
            ->first();

        if ($dailyDownload) {
            DB::table('daily_downloads')
                ->where('link_id', $linkInfo->id)
                ->where('download_date', $today)
                ->update([
                    'downloads' => $dailyDownload->downloads + 1
                ]);
        } else {
            DailyDownload::create([
                'user_id' => $linkInfo->user_id,
                'link_id' => $linkInfo->id,
                'passdrop_url' => $linkInfo->passdrop_url,
                'downloads' => 1,
                'download_date' => $today
            ]);
        }

        if ($linkInfo->email_notify != 0 || $linkInfo->track_ip != 0) {
            $ipAddress = $request->ip();
            $url = 'http://api.ipstack.com/'.$ipAddress.'?access_key='.env('IP_TRACK_KEY').'&format=1';
            $locationInfo = Curl::to($url)
                ->asJson(true)
                ->get();

            if ($linkInfo->track_ip != 0) {
                IpTracker::create([
                    'link_id' => $linkInfo->id,
                    'ip' => $ipAddress,
                    'country' => $locationInfo['country_name'],
                    'city' => $locationInfo['city'],
                    'latlong' => $locationInfo['latitude'].','.$locationInfo['longitude']
                ]);
            }

            if ($linkInfo->email_notify != 0 && !empty($linkInfo->user_id)) {
                $owner = User::where('id', $linkInfo->user_id);

                $isPassdropitRequest = $request->is('api/'.config('app.api-version').'/passdropit/*');
                $fromAddress = $isPassdropitRequest ? env('MAIL_FROM_PASSDROPIT_ADDRESS') : env('MAIL_FROM_NOTIONS11_ADDRESS');
                $fromName = $isPassdropitRequest ? 'Passdropit Notification' : 'Notions11 Notification';
                $mailText = 'Your';
                $mailText .= $isPassdropitRequest ? ' Passdropit' : ' Notions11';
                $mailText .= ' link ';
                $mailText .= $isPassdropitRequest ? config('constants.site_url.passdropit') : config('constants.site_url.notions11');
                $mailText .= '/'.$linkInfo->passdrop_url.' was just accessed by a user in';
                $mailText .= $locationInfo['city'].' '.$locationInfo['country_name'];

                $owner->notify(new LinkDownload($fromAddress, $fromName, $mailText));
            }
        }

        return response()->json([
            'success' => true
        ]);
    }
}
