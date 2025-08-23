<?php

namespace App\Http\Controllers;
use Illuminate\Http\Request;
use App\Models\WebhookEvent;
use App\Jobs\ProcessWebhook;
class WebhookController extends Controller {
    public function pesapal(Request $request){ 
        return $this->ingest('pesapal',$request); 
    }
    public function flutterwave(Request $request){ 
        return $this->ingest('flutterwave', $request); 
    }
    private function ingest(string $provider, Request $request){
        $event = WebhookEvent::create([
            'provider'=>$provider,
            'event_type'=>$request->input('event','deposit'),
            'signature'=>$request->header('X-Signature'),
            'headers'=>json_encode($request->headers->all()),
            'payload'=>json_encode($request->all()),
        ]);
        // For first run, process inline to avoid queue setup
        dispatch_sync(new ProcessWebhook($event->id));
        return response()->json(['ok'=>true]);
    }
}
