<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiController extends Controller
{
    public function test()
    {
        $tokens = DB::connection('mysql')->select('select * from module_cdamp.tokens;');
        return response()->json($tokens);
    }

    public function conversation(Request $request)
    {

    }
}
