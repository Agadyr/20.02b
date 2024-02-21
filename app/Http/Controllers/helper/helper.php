<?php

namespace App\Http\Controllers\helper;

use Illuminate\Support\Facades\DB;

class helper
{
    public static $pattern = '/<EOF> Заняло (\d+) мс/';

    public static function checkToken($tokenHeader)
    {
        $tokens = DB::connection('mysql')->select('select * from module_cdamp.tokens');
        $isValid = false;
        foreach ($tokens as $token) {
            if ($token->token == $tokenHeader) {
                $isValid = true;
            }
        }
        return $isValid;
    }

    public static function getErrorResponse($request)
    {
        $status = $request->getResponse()->getStatusCode();
        return response()->json(helper::getErrorResponseDataByStatus($status), $status);
    }

    public static function checkQuota($tokenHeader)
    {
        $token = DB::connection('mysql')->table('module_cdamp.tokens')->where('token', $tokenHeader)->first();
        $workspace = DB::connection('mysql')->table('module_cdamp.workspaces')->where('id', $token->workspace)->first();
        $quota = DB::connection('mysql')->table('module_cdamp.billingquotas')->where('workspace', $workspace->id)->first();
        $bills = DB::connection('mysql')->table('module_cdamp.billings')->where('token', $token->id)->sum('total');

        if (!$quota) {
            return true;
        }

        if ($bills > $quota->limit) {
            return false;
        }

        return true;
    }

    public static function addUsage($ms,$headerToken,$serviceId)
    {
        $token = DB::connection('mysql')->table('module_cdamp.tokens')->where('token', $headerToken)->first();
        $data = [
            'duration_in_ms' => $ms,
            'api_token_id' => $token->id,
            'service_id' => $serviceId,
            'usage_started_at' => now(),
        ];
        DB::connection('mysql')->table('module_cdamp.service_usages')->insert($data);
    }

    public static function getErrorResponseDataByStatus($status)
    {
        if ($status == '400') {
            return [
                "type" => "/problem/types/400",
                "title" => "Bad Request",
                "status" => 400,
                "detail" => "The request is invalid."
            ];
        } else if ($status == '401') {
            return [
                "type" => "/problem/types/401",
                "title" => "Unauthorized",
                "status" => 401,
                "detail" => "The header X-API-TOKEN is missing or invalid."
            ];
        } else if ($status == '403') {
            return [
                "type" => "/problem/types/403",
                "title" => "Quota Exceeded",
                "status" => 403,
                "detail" => "You have exceeded your quota."
            ];
        } else {
            return [
                "type" => "/problem/types/503",
                "title" => "Service Unavailable",
                "status" => 503,
                "detail" => "The service is currently unavailable."
            ];
        }
    }


}
