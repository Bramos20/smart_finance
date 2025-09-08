<?php

namespace App\Domain\Payments;

use App\Models\User;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class PesapalProvider implements PaymentProvider
{
    public function getAccessToken(): string
    {
        return Cache::remember('pesapal_token', 3500, function () {
            $baseUrl = config('services.pesapal.base_url');
            $consumerKey = config('services.pesapal.consumer_key');
            $consumerSecret = config('services.pesapal.consumer_secret');

            if (!$baseUrl || !$consumerKey || !$consumerSecret) {
                throw new RuntimeException('Missing Pesapal configuration. Check your .env file.');
            }

            Log::info('Requesting Pesapal token', [
                'base_url' => $baseUrl,
                'consumer_key' => $consumerKey ? 'present' : 'missing'
            ]);

            $response = Http::timeout(30)->post($baseUrl.'/api/Auth/RequestToken', [
                'consumer_key' => $consumerKey,
                'consumer_secret' => $consumerSecret,
            ]);

            if (!$response->ok()) {
                Log::error('Failed to get Pesapal token', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new RuntimeException('Failed to get Pesapal token: '.$response->body());
            }

            $data = $response->json();
            if (!isset($data['token'])) {
                Log::error('No token in Pesapal response', ['response' => $data]);
                throw new RuntimeException('No token from Pesapal: '.json_encode($data));
            }

            Log::info('Successfully got Pesapal token');
            return $data['token'];
        });
    }

    public function initiateDeposit(User $user, Money $amount, array $meta = []): ProviderIntent
    {
        try {
            $token = $this->getAccessToken();
            $baseUrl = config('services.pesapal.base_url');
            $callbackUrl = config('services.pesapal.callback_url') ?: url('/deposits/callback');
            $ipnId = config('services.pesapal.ipn_id');

            if (!$ipnId) {
                throw new RuntimeException('PESAPAL_IPN_ID is not configured. Please run: php artisan pesapal:register-ipn');
            }

            // Generate unique order ID
            $orderId = 'SF-' . time() . '-' . $user->id;

            // Minimal payload that should work
            $payload = [
                'id' => $orderId,
                'currency' => $amount->currency,
                'amount' => (float) $amount->amount,
                'description' => 'Smart Finance Deposit',
                'callback_url' => $callbackUrl,
                'notification_id' => $ipnId,
                'billing_address' => [
                    'email_address' => $user->email,
                    'phone_number' => $user->phone ?? '254700000000', // Remove + prefix
                    'country_code' => 'KE',
                    'first_name' => explode(' ', trim($user->name))[0] ?? 'User',
                    'middle_name' => '',
                    'last_name' => count(explode(' ', trim($user->name))) > 1 ? 
                                  explode(' ', trim($user->name))[1] : 'SmartFinance',
                    'line_1' => 'Nairobi',
                    'line_2' => '',
                    'city' => 'Nairobi',
                    'state' => 'Nairobi',
                    'postal_code' => '00100',
                    'zip_code' => '00100'
                ],
            ];

            Log::info('Submitting Pesapal order', [
                'user_id' => $user->id,
                'amount' => $amount->amount,
                'order_id' => $orderId,
                'payload' => $payload
            ]);

            $response = Http::withToken($token)
                ->timeout(30)
                ->post($baseUrl.'/api/Transactions/SubmitOrderRequest', $payload);

            Log::info('Pesapal order response', [
                'status' => $response->status(),
                'headers' => $response->headers(),
                'body' => $response->body()
            ]);

            if (!$response->ok()) {
                Log::error('Pesapal order failed', [
                    'status' => $response->status(),
                    'body' => $response->body()
                ]);
                throw new RuntimeException('Pesapal API error (HTTP '.$response->status().'): '.$response->body());
            }

            $data = $response->json();

            // Check if there's an error in the response
            if (isset($data['error'])) {
                Log::error('Pesapal returned error', ['error' => $data['error']]);
                $errorMsg = $data['error']['message'] ?? 'Unknown Pesapal error';
                throw new RuntimeException('Pesapal error: ' . $errorMsg);
            }

            if (!isset($data['redirect_url'])) {
                Log::error('No redirect_url in successful response', ['response' => $data]);
                throw new RuntimeException('Pesapal did not return redirect_url. Response: '.json_encode($data));
            }

            Log::info('Pesapal order successful', [
                'redirect_url' => $data['redirect_url'],
                'order_tracking_id' => $data['order_tracking_id'] ?? 'N/A'
            ]);

            return new ProviderIntent('pesapal', 'redirect', $data['redirect_url']);

        } catch (\Exception $e) {
            Log::error('Exception in Pesapal initiateDeposit', [
                'user_id' => $user->id,
                'amount' => $amount->amount,
                'exception' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // In development, fall back to a dev payment page
            if (app()->environment('local')) {
                Log::info('Falling back to dev payment for local environment');
                return new ProviderIntent('pesapal', 'redirect', 
                    url('/dev-payment?amount=' . $amount->amount . '&user_id=' . $user->id));
            }
            
            throw $e;
        }
    }

    public function handleWebhook(Request $request): ProviderEvent
    {
        Log::info('Processing Pesapal webhook', [
            'method' => $request->method(),
            'headers' => $request->headers->all(),
            'body' => $request->all()
        ]);

        return new ProviderEvent(
            provider: 'pesapal',
            status: $request->input('status', 'failed'),
            amount: new Money($request->input('currency', 'KES'), $request->input('amount', '0')),
            reference: $request->input('reference', ''),
            meta: $request->all()
        );
    }
}