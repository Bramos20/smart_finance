<?php

namespace App\Jobs;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\{WebhookEvent, User};
use App\Domain\Payments\ProviderFactory;
use App\Services\DepositService;
use Illuminate\Http\Request;

class ProcessWebhook implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public function __construct(public int $webhookId){}
    public function handle(DepositService $deposits, ProviderFactory $factory):void {
        $event = WebhookEvent::findOrFail($this->webhookId);
        $provider = $factory->for($event->provider);
        $req = new Request(json_decode($event->payload, true) ?? []);
        $pe = $provider->handleWebhook($req);
        $userId = data_get($pe->meta,'user_id');
        $user = User::findOrFail($userId);
        $deposits->recordSuccessfulDeposit($user, $pe);
        $event->update(['status'=>'processed','processed_at'=>now()]);
    }
}
