<?php

namespace App\Http\Controllers\helper;

use Doctrine\Inflector\Rules\NorwegianBokmal\Rules;
use Illuminate\Support\Facades\DB;

class helper
{
    public static function checkToken($tokenHeader)
    {
        $tokens = DB::connection('mysql')->select('select * from module_cdamp');
        $isValid = false;
        foreach ($tokens as $token) {
            if ($token->token == $tokenHeader) {
                $isValid = true;
            }
        }
        return $isValid;
    }

}
