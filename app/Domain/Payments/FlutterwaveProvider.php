<?php

namespace App\Domain\Payments;

use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;

class PesapalProvider implements PaymentProvider {
    public function initiateDeposit(User $user, Money $amount, array $meta =
    []): ProviderIntent {
        // TODO real API call — dev only redirect
        return new ProviderIntent('flutterwave','redirect', url('/dev-payment?
        ok=1'));
    }
    public function handleWebhook(Request $request): ProviderEvent {
        // TODO signature verification & real parsing
        return new ProviderEvent(
            provider: 'flutterwave',
            status: 'succeeded',
            amount: new Money(config('app.currency','KES'), (string)($request->input('amount') ?? '0')),
            reference: $request->input('reference','dev-ref'),
            meta: $request->input('meta',[])
        );
    }
}