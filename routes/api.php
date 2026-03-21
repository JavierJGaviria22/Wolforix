<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\LeadController;
use App\Http\Controllers\Api\OrderController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| These routes are loaded by the RouteServiceProvider and all of them
| will be assigned to the "api" middleware group.
|
*/

Route::prefix('v1')->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Health check
    |--------------------------------------------------------------------------
    */

    Route::get('/health', function () {
        return response()->json([
            'status' => 'ok',
            'service' => 'whatsapp-ai-api',
            'timestamp' => now()
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | WhatsApp Messages
    |--------------------------------------------------------------------------
    */

    Route::post('/messages/incoming', [MessageController::class, 'incoming']);
    Route::post('/messages/outgoing', [MessageController::class, 'outgoing']);

    /*
    |--------------------------------------------------------------------------
    | Leads (cuando IA detecta intención de compra)
    |--------------------------------------------------------------------------
    */

    Route::post('/leads/store', [LeadController::class, 'store']);

    /*
    |--------------------------------------------------------------------------
    | Pedidos 
    |--------------------------------------------------------------------------
    */

    Route::post('/orders/new', [OrderController::class, 'new']);

});