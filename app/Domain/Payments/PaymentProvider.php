<?php

namespace App\Domain\Payments;

use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;

interface PaymentProvider {
    public function initiateDeposit(User $user, Money $amount, array $meta =
    []): ProviderIntent;
    public function handleWebhook(Request $request): ProviderEvent;
}