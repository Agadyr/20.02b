<?php

use App\Http\Controllers\Api\ApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/getAllTokens', [ApiController::class, 'test']);

Route::prefix('/chat')->group(function () {
    Route::post('/conversation', [ApiController::class, 'conversation']);
    Route::post('/conversation/{conversation:conversation_id}', [ApiController::class, 'continueConversation']);
    Route::get('/conversation/{conversation:conversation_id}', [ApiController::class, 'getPartConversation']);
});

Route::prefix('/imagegeneration')->group(function () {
    Route::post('/generation', [ApiController::class, 'generateImage']);
    Route::post('/upscale', [ApiController::class, 'upscaleImage']);
    Route::post('/zoomin', [ApiController::class, 'zoomIn']);
    Route::post('/zoomOut', [ApiController::class, 'zoomOut']);
    Route::get('/getStatusJob/{job:job_id}', [ApiController::class, 'getStatusJob']);
    Route::get('/getResultJob/{job:job_id}', [ApiController::class, 'getResultJob']);
});
