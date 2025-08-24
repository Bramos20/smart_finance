<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;

class PesapalWebhookController extends Controller
{
    public function handle(Request $request)
    {
        Log::info('Pesapal IPN hit', [
            'method' => $request->method(),
            'query'  => $request->query(),
            'json'   => $request->json()->all(),
            'raw'    => $request->getContent(),
            'headers'=> $request->headers->all(),
        ]);

        // For Phase 1 we just 200 OK; later we’ll verify and process.
        return response()->json(['ok' => true]);
    }
}
