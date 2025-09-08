<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Domain\Payments\PesapalProvider;
use App\Models\User;
use App\Support\Money;

class DebugPesapal extends Command
{
    protected $signature = 'pesapal:debug';
    protected $description = 'Debug Pesapal integration';

    public function handle(): int
    {
        $this->info('=== Pesapal Debug ===');
        
        // Check configuration
        $this->info('1. Checking configuration...');
        $config = [
            'base_url' => config('services.pesapal.base_url'),
            'consumer_key' => config('services.pesapal.consumer_key') ? 'SET' : 'NOT SET',
            'consumer_secret' => config('services.pesapal.consumer_secret') ? 'SET' : 'NOT SET',
            'ipn_id' => config('services.pesapal.ipn_id') ?: 'NOT SET',
        ];
        
        foreach ($config as $key => $value) {
            $this->line("  {$key}: {$value}");
        }
        
        if ($config['consumer_key'] === 'NOT SET' || $config['consumer_secret'] === 'NOT SET') {
            $this->error('Missing Pesapal credentials in .env file');
            return self::FAILURE;
        }
        
        // Test token generation
        $this->info('2. Testing token generation...');
        try {
            $provider = app(PesapalProvider::class);
            $token = $provider->getAccessToken();
            $this->info('  ✓ Token generated successfully');
            $this->line('  Token: ' . substr($token, 0, 20) . '...');
        } catch (\Exception $e) {
            $this->error('  ✗ Failed to generate token: ' . $e->getMessage());
            return self::FAILURE;
        }
        
        // Test order submission with a test user
        $this->info('3. Testing order submission...');
        try {
            $user = User::first();
            if (!$user) {
                $this->error('  No users found. Please register a user first.');
                return self::FAILURE;
            }
            
            $this->line('  Using user: ' . $user->email);
            
            $money = new Money('KES', '100'); // Small test amount
            $intent = $provider->initiateDeposit($user, $money);
            
            $this->info('  ✓ Order submitted successfully');
            $this->line('  Redirect URL: ' . $intent->link);
            
        } catch (\Exception $e) {
            $this->error('  ✗ Failed to submit order: ' . $e->getMessage());
            $this->line('  This is the error you need to fix.');
            return self::FAILURE;
        }
        
        $this->info('=== All tests passed! ===');
        return self::SUCCESS;
    }
}