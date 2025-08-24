<?php

namespace App\Domain\Payments;

use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class PesapalProvider implements PaymentProvider {
    // public function initiateDeposit(User $user, Money $amount, array $meta =
    // []): ProviderIntent {
    //     // TODO real API call — dev only redirect
    //     return new ProviderIntent('pesapal','redirect', url('/dev-payment?ok=1'));
    // }
    // public function handleWebhook(Request $request): ProviderEvent {
    //     // TODO signature verification & real parsing
    //     return new ProviderEvent(
    //         provider: 'pesapal',
    //         status: 'succeeded',
    //         amount: new Money(config('app.currency','KES'), (string)($request->input('amount') ?? '0')),
    //         reference: $request->input('reference','dev-ref'),
    //         meta: $request->input('meta',[])
    //     );
    // }

    public function initiateDeposit(User $user, Money $amount, array $meta = []): ProviderIntent {
        $url = config('services.pesapal.base_url') . '/api/Transactions/SubmitOrderRequest';

        $payload = [
            'Amount' => $amount->amount,
            'Currency' => $amount->currency,
            'Description' => 'Smart Finance Deposit',
            'CallbackUrl' => route('webhooks.pesapal'),
            'Reference' => uniqid('TXN-'),
            'CustomerEmail' => $user->email,
        ];

        // Call Pesapal API
        $response = Http::withHeaders([
            'Authorization' => 'Bearer ' . config('services.pesapal.token')
        ])->post($url, $payload)->json();

        return new ProviderIntent('pesapal', 'redirect', $response['redirect_url'] ?? '/');
    }

    public function handleWebhook(Request $request): ProviderEvent {
        // Verify signature
        $signature = $request->header('X-Signature');
        if ($signature !== config('services.pesapal.webhook_secret')) {
            throw new \RuntimeException('Invalid signature');
        }

        return new ProviderEvent(
            provider: 'pesapal',
            status: $request->input('status','failed'),
            amount: new Money($request->input('currency','KES'), $request->input('amount','0')),
            reference: $request->input('reference',''),
            meta: $request->input('meta',[])
        );
    }

}