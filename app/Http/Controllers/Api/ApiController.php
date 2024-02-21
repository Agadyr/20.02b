<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\helper\helper;
use App\Models\Conversation;
use Dotenv\Validator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpParser\Node\Stmt\Finally_;
use Psy\Util\Str;

class ApiController extends Controller
{
    public function test()
    {
        $tokens = DB::connection('mysql')->select('select * from module_cdamp.tokens;');
        return response()->json($tokens);
    }

    public function conversation(Request $request)
    {
        if (!helper::checkToken($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('401');
        }
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'prompt' => 'required'
        ]);

        if ($validator->fails()) {
            return helper::getErrorResponseDataByStatus('400');
        }
        $new_conversation = new Conversation();
        $new_conversation->conversation_id = \Illuminate\Support\Str::random('20');
        $new_conversation->save();

        $client = new Client();

        try {
            $response = $client->post('http://localhost:8001/api/conversation',
                [
                    'json' => [
                        'conversationId' => $new_conversation->conversation_id
                    ]
                ]);
        } catch (RequestException $requestException) {
            if ($requestException->hasResponse()) {
                helper::getErrorResponse($requestException);
            }
        }

        try {
            $response2 = $client->post('http://localhost:8001/api/conversation/' . $new_conversation->conversation_id, [
                'form_params' => [
                    'text' => $request->get('prompt')
                ]
            ]);
        } catch (RequestException $requestException) {
            if ($requestException->hasResponse()) {
                return helper::getErrorResponse($requestException);
            }
        }

        $data = json_decode($response2->getBody()->getContents());

        return response()->json([
            'conversation_id' => $new_conversation->conversation_id,
            'response' => $data->response,
            'is_final' => $data->is_final
        ]);
    }

    public function continueConversation(Conversation $conversation, Request $request)
    {
        if (!helper::checkToken($request->header('x-api-token'))) {
            return helper::getErrorResponse('401');
        }

        if (!helper::checkQuota($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('403');
        }

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'prompt' => 'required'
        ]);
        if ($validator->fails()) {
            return helper::getErrorResponse('400');
        }

        $client = new Client();


        if (!$conversation->is_final) {
            try {
                $response1 = $client->get('http://localhost:8001/api/conversation/' . $conversation->conversation_id);
            } catch (RequestException $requestException) {
                if ($requestException->hasResponse()) {
                    return helper::getErrorResponse($requestException);
                }
            }
            $data = json_decode($response1->getBody()->getContents());
            if (str_contains($data, '<EOF>')) {
                $conversation->is_final = true;
                $conversation->save();
            }else{
                return helper::getErrorResponseDataByStatus('503');
            }
        }


        try {
            $response2 = $client->post('http://localhost:8001/api/conversation/' . $conversation->conversation_id, [
                'form_params' => [
                    'text' => $request->get('prompt')
                ]
            ]);
        } catch (RequestException $requestException) {
            if ($requestException->hasResponse()) {
                return helper::getErrorResponse($requestException);
            }
        }
        $conversation->is_final = false;

        $conversation->save();

        $data = json_decode($response2->getBody()->getContents());

        return response()->json([
            'conversation_id' => $conversation->conversation_id,
            'response' => $data->response,
            'is_final' => $data->is_final,
        ]);
    }

}
