<?php

// app/Console/Commands/PesapalRegisterIpn.php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class PesapalRegisterIpn extends Command
{
    protected $signature = 'pesapal:register-ipn {--url=} {--method=GET}';
    protected $description = 'Register IPN URL with Pesapal and print ipn_id';

    public function handle(): int
    {
        $base  = config('services.pesapal.base_url');
        $key   = config('services.pesapal.consumer_key');
        $secret= config('services.pesapal.consumer_secret');

        if (!$base || !$key || !$secret) {
            $this->error('Missing PESAPAL_BASE_URL / CONSUMER_KEY / CONSUMER_SECRET in .env');
            return self::FAILURE;
        }

        // 1) Get OAuth token
        $auth = Http::asJson()->post($base.'/api/Auth/RequestToken', [
            'consumer_key'    => $key,
            'consumer_secret' => $secret,
        ]);

        if (!$auth->successful()) {
            $this->error('Auth failed: '.$auth->body());
            return self::FAILURE;
        }
        $token = $auth->json('token');
        if (!$token) {
            $this->error('No token returned: '.$auth->body());
            return self::FAILURE;
        }

        // 2) Pick URL
        $ipnUrl = $this->option('url') ?: route('webhooks.pesapal');
        $method = strtoupper($this->option('method') ?: 'GET');

        // 3) Register IPN
        $resp = Http::asJson()
            ->withToken($token)
            ->post($base.'/api/URLSetup/RegisterIPN', [
                'url' => $ipnUrl,
                'ipn_notification_type' => $method, // GET or POST
            ]);

        $this->info('Status: '.$resp->status());
        $this->line($resp->body());

        if (!$resp->successful()) {
            $this->error('Failed to register IPN.');
            return self::FAILURE;
        }

        $id = $resp->json('ipn_id');
        if ($id) {
            $this->newLine();
            $this->info('✅ IPN registered. Copy this into .env as PESAPAL_IPN_ID:');
            $this->warn($id);
        } else {
            $this->error('No ipn_id in response.');
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
