<?php

use Illuminate\Http\Request;

use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

Route::post('/webhooks/pesapal', [WebhookController::class,'pesapal']);
Route::post('/webhooks/flutterwave', [WebhookController::class,'flutterwave']);