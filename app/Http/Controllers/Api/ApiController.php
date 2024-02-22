<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\helper\helper;
use App\Models\Conversation;
use App\Models\Job;
use Carbon\Carbon;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use http\Env\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

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
        if (!helper::checkToken($request->header('x-api-token'))) {
            return helper::getErrorResponse('401');
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
            } else {
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

    public function getPartConversation(Request $request, Conversation $conversation)
    {
        if (!helper::checkToken($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('401');
        }

        if (!helper::checkQuota($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('403');
        }

        $client = new Client();

        try {
            $response = $client->get('http://localhost:8001/api/conversation/' . $conversation->conversation_id,
                [
                    'json' => [
                        'prompt' => $request->get('prompt')
                    ]
                ]);
        } catch (RequestException $requestException) {
            if ($requestException->hasResponse()) {
                return helper::getErrorResponse($requestException);
            }
        }

        $data = json_decode($response->getBody()->getContents());
        $final = false;


        if (preg_match(helper::$pattern, $data, $matches)) {
            if ($matches[1]) {
                $millis = ((int)$matches[1]);
                $final = true;
                helper::addUsage($millis, $request->header('x-api-token'), 1);
            }
        }

        if ($final) {
            $conversation->is_final = true;
            $conversation->save();
        }

        return \response()->json([
            'conversation_id' => $conversation->conversation_id,
            'response' => $data,
            'is_final' => $conversation->is_final
        ]);

    }

    public function generateImage(Request $request)
    {
        if (!helper::checkToken($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('401');
        }

        if (!helper::checkQuota($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('403');
        }

        $validator = Validator::make($request->all(), [
            'text_prompt' => 'required'
        ]);

        if ($validator->fails()) {
            return helper::getErrorResponseDataByStatus('503');
        }

        $client = new Client();

        try {
            $response = $client->post('http://localhost:8001/api/generate', [
                'form_params' => [
                    'text_prompt' => $request->get('text_prompt')
                ]
            ]);
        } catch (RequestException $requestException) {
            if ($requestException->hasResponse()) {
                return helper::getErrorResponse($requestException);
            }
        }

        $data = json_decode($response->getBody()->getContents());

        $job = Job::create([
            'job_id' => $data->job_id,
            'created_at' => $data->started_at,
        ]);

        return \response()->json([
            'job_id' => $job->job_id,
            'created_at' => $job->created_at
        ]);

    }

    public function getStatusJob(Request $request, Job $job)
    {
        if (!helper::checkToken($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('401');
        }

        if (!helper::checkQuota($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('403');
        }

        $client = new Client();

        try {
            $response = $client->get("http://localhost:8001/api/status/$job->job_id");
        } catch (RequestException $requestException) {
            if ($requestException->hasResponse()) {
                return helper::getErrorResponse($requestException);
            }
        }

        $data = json_decode($response->getBody()->getContents());

        $job->preview_url = $data->image_url;

        if (!$job->preview_local_url) {
            $path = 'uploads/' . Str::random(10) . '_img.jpg';
            $img = file_get_contents($data->image_url);
            Storage::disk('public')->put($path, $img);
            $job->preview_local_url = $path;
        }
        if ($data->progress == 100) {
            $job->is_final = true;
        }

        $job->save();

        return \response()->json([
            'status' => $data->status,
            'progress' => $data->progress,
            'image_url' => $data->image_url
        ]);

    }

    public function getResultJob(Request $request, Job $job)
    {
        if (!helper::checkToken($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('401');
        }

        if (!helper::checkQuota($request->header('x-api-token'))) {
            return helper::getErrorResponseDataByStatus('403');
        }

        $client = new Client();

        try {
            $response = $client->get('http://localhost:8001/api/result/' . $job->job_id);
        } catch (RequestException $requestException) {
            if ($requestException->hasResponse()) {
                return helper::getErrorResponse($requestException);
            }
        }

        $data = json_decode($response->getBody()->getContents());

        $job->is_final = true;
        $job->resource_id = $data->resource_id;
        $job->image_url = $data->image_url;
        $job->save();

        if (!$job->image_local_url) {
            $path = 'uploads/' . Str::random(10) . '_img.jpg';
            $img = file_get_contents($data->image_url);
            Storage::disk('public')->put($path, $img);
            $job->image_local_url = $path;
            $job->save();
        }
        if ($data->finished_at) {
            $finished_at = Carbon::parse($data->finished_at);
            $seconds = $finished_at->diff($job->created_at)->s;
            $ms = ((int)$seconds * 1000);
            helper::addUsage($ms, $request->header('x-api-token'), 2);
        }

        return \response()->json([
            'resourse_id' => $data->resource_id,
            'image_url' => $data->image_url
        ]);

    }



}
