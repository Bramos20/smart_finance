<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use App\Services\DepositService;
use App\Domain\Payments\{ProviderFactory, ProviderEvent};
use App\Support\Money;
use App\Models\{WebhookEvent, User};
use Illuminate\Support\Facades\Http;

class PesapalWebhookController extends Controller
{
    public function __construct(
        private DepositService $depositService,
        private ProviderFactory $providerFactory
    ) {}

    public function handle(Request $request)
    {
        Log::info('Pesapal webhook received', [
            'method' => $request->method(),
            'query' => $request->query(),
            'body' => $request->all(),
            'headers' => $request->headers->all(),
        ]);

        try {
            // Get the order tracking ID from the request
            $orderTrackingId = $request->input('OrderTrackingId') ?? $request->query('OrderTrackingId');
            $merchantReference = $request->input('OrderMerchantReference') ?? $request->query('OrderMerchantReference');
            
            if (!$orderTrackingId) {
                Log::warning('No OrderTrackingId in webhook');
                return response()->json(['status' => 'error', 'message' => 'No OrderTrackingId']);
            }

            Log::info('Processing Pesapal webhook', [
                'order_tracking_id' => $orderTrackingId,
                'merchant_reference' => $merchantReference
            ]);

            // Get transaction status from Pesapal
            $transactionStatus = $this->getTransactionStatus($orderTrackingId);
            
            if (!$transactionStatus) {
                Log::error('Failed to get transaction status', ['order_id' => $orderTrackingId]);
                return response()->json(['status' => 'error', 'message' => 'Failed to get transaction status']);
            }

            Log::info('Transaction status retrieved', $transactionStatus);

            // Check if payment was successful
            $paymentStatus = $transactionStatus['payment_status_description'] ?? '';
            
            if (in_array($paymentStatus, ['Completed', 'COMPLETED'])) {
                // Extract user ID from merchant reference (format: SF-timestamp-userID)
                $parts = explode('-', $merchantReference);
                $userId = end($parts);

                $user = User::find($userId);
                if (!$user) {
                    Log::error('User not found for order', [
                        'merchant_reference' => $merchantReference, 
                        'user_id' => $userId
                    ]);
                    return response()->json(['status' => 'error', 'message' => 'User not found']);
                }

                // Check if we already processed this transaction
                $existingTx = $user->transactions()
                    ->where('provider_ref', $orderTrackingId)
                    ->where('status', 'succeeded')
                    ->first();

                if ($existingTx) {
                    Log::info('Transaction already processed', ['transaction_id' => $existingTx->id]);
                    return response()->json(['status' => 'success', 'message' => 'Already processed']);
                }

                // Create ProviderEvent
                $providerEvent = new ProviderEvent(
                    provider: 'pesapal',
                    status: 'succeeded',
                    amount: new Money(
                        $transactionStatus['currency'] ?? 'KES', 
                        (string) $transactionStatus['amount']
                    ),
                    reference: $orderTrackingId,
                    meta: array_merge($transactionStatus, [
                        'user_id' => $userId,
                        'merchant_reference' => $merchantReference
                    ])
                );

                // Record the deposit
                $transaction = $this->depositService->recordSuccessfulDeposit($user, $providerEvent);
                
                Log::info('Deposit recorded successfully', [
                    'transaction_id' => $transaction->id,
                    'user_id' => $user->id,
                    'amount' => $transactionStatus['amount']
                ]);

            } else {
                Log::info('Payment not completed', [
                    'status' => $paymentStatus,
                    'order_id' => $orderTrackingId
                ]);
            }

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Webhook processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    private function getTransactionStatus(string $orderTrackingId): ?array
    {
        try {
            $provider = $this->providerFactory->for('pesapal');
            $token = $provider->getAccessToken();
            $baseUrl = config('services.pesapal.base_url');

            Log::info('Getting transaction status from Pesapal', [
                'order_tracking_id' => $orderTrackingId,
                'base_url' => $baseUrl
            ]);

            $response = Http::withToken($token)
                ->timeout(30)
                ->get($baseUrl . '/api/Transactions/GetTransactionStatus', [
                    'orderTrackingId' => $orderTrackingId
                ]);

            Log::info('Pesapal status response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Failed to get transaction status', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('Exception getting transaction status', [
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
}