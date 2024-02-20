<?php

namespace App\Http\Controllers\helper;

use Doctrine\Inflector\Rules\NorwegianBokmal\Rules;
use Illuminate\Support\Facades\DB;

class helper
{
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
        return response()->json(helper::getErrorResponseDataByStatus($status),$status);
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
        }else if ($status == '401') {
            return [
                "type" => "/problem/types/401",
                "title" => "Unauthorized",
                "status" => 401,
                "detail" => "The header X-API-TOKEN is missing or invalid."
            ];
        }else if ($status == '403') {
            return [
                "type" => "/problem/types/403",
                "title" => "Quota Exceeded",
                "status" => 403,
                "detail" => "You have exceeded your quota."
            ];
        }else{
            return [
                "type" => "/problem/types/503",
                "title" => "Service Unavailable",
                "status" => 503,
                "detail" => "The service is currently unavailable."
            ];
        }
    }


}
