<?php

namespace App\Domain\Payments;

use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class PesapalProvider implements PaymentProvider
{
    protected function getAccessToken(): string
    {
        return Cache::remember('pesapal_token', 3500, function () {
            $response = Http::post(config('services.pesapal.base_url').'/api/Auth/RequestToken', [
                'consumer_key' => config('services.pesapal.consumer_key'),
                'consumer_secret' => config('services.pesapal.consumer_secret'),
            ]);

            if (!$response->ok()) {
                throw new RuntimeException('Failed to get Pesapal token: '.$response->body());
            }

            return $response->json()['token'] ?? throw new RuntimeException('No token from Pesapal');
        });
    }

    public function initiateDeposit(User $user, Money $amount, array $meta = []): ProviderIntent
    {
        $token = $this->getAccessToken();

        $payload = [
            'id' => uniqid(), // unique ID for this order
            'currency' => $amount->currency,
            'amount' => (float) $amount->amount,
            'description' => 'Smart Finance Deposit',
            'callback_url' => config('services.pesapal.callback_url'),
            'notification_id' => config('services.pesapal.ipn_id'),
            'branch' => 'default',
            'billing_address' => [
                'email_address' => $user->email,
                'phone_number' => $user->phone ?? '254700000000',
                'first_name' => $user->name ?? 'User',
                'last_name' => 'SmartFinance',
            ],
        ];

        $response = Http::withToken($token)
            ->post(config('services.pesapal.base_url').'/api/Transactions/SubmitOrderRequest', $payload);

        if (!$response->ok()) {
            throw new RuntimeException('Pesapal error: '.$response->body());
        }

        $data = $response->json();

        if (!isset($data['redirect_url'])) {
            throw new RuntimeException('Pesapal did not return redirect_url. Response: '.json_encode($data));
        }

        return new ProviderIntent('pesapal', 'redirect', $data['redirect_url']);
    }

    public function handleWebhook(Request $request): ProviderEvent
    {
        return new ProviderEvent(
            provider: 'pesapal',
            status: $request->input('status', 'failed'),
            amount: new Money($request->input('currency', 'KES'), $request->input('amount', '0')),
            reference: $request->input('reference', ''),
            meta: $request->all()
        );
    }
}
