<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Ixudra\Curl\Facades\Curl;
use App\Models\FileListUser;
use function Monolog\toArray;

class LinkController extends Controller
{
    public function checkGoogleLink(Request $request): JsonResponse {
        $request->validate([
            'url' => ['required']
        ]);

        $url = $request->string('url')->value();
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
            'link_type' => 'required',
            'email_notify' => 'required',
            'track_ip' => 'required',
            'cost' => 'required|numeric',
            'expiry_count' => 'required',
        ]);

        $files = $request->input('files');
        $service = $request->integer('service', 0);
        $link = $request->string('link')->value();
        $password = $request->string('password')->value();
        $linkType = $request->string('link_type')->value();
        $blTrackIp = $request->boolean('track_ip', false);
        $blEmailNotify = $request->boolean('email_notify', false);
        $cost = $request->integer('cost', 0);
        $expiryCount = $request->integer('expiry_count', 0);
        $expiryOn = $request->string('expiry_on')->value();

        $isExists = FileListUser::where('passdrop_url', $link)
            ->get()
            ->count();
        if ($isExists) {
            return response()->json([
                'success' => false,
                'message' => 'Sorry that URL is taken, please try another'
            ]);
        }

        $url = Arr::join(
            Arr::map($files, function (array $file) {
                return $file['url'];
            }),
            ','
        );

        $fileName = '';
        if (!empty($files[0]['name'])) {
            $fileName = Arr::join(
                Arr::map($files, function (array $file) {
                    return $file['name'];
                }),
                ','
            );
        }

        $ret = FileListUser::create([
            'dropbox_url' => $url,
            'passdrop_url' => $link,
            'passdrop_pwd' => $password,
            'service' => config('constants.service_type')[$service],
            'filename' => $fileName,
            'user_id' => auth('sanctum')->user() ? auth('sanctum')->user()->id : 0,
            'is_verified' => config('constants.is_verified'),
            'download_count' => 0,
            'link_type' => $linkType,
            'email_notify' => $blEmailNotify ? 1 : 0,
            'track_ip' => $blTrackIp ? 1 : 0,
            'expire_count' => $expiryCount,
            'expires_on' => empty($expiryOn) ? null : $expiryOn,
            'is_paid' => empty($cost) ? null : $cost,
            'created_on' => date('Y-m-d')
        ]);

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

}
