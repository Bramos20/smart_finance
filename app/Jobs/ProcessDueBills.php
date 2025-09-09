<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Services\BillService;
use Illuminate\Support\Facades\Log;

class ProcessDueBills implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BillService $billService): void
    {
        Log::info('Starting due bills processing job');
        
        try {
            $processed = $billService->processDueBills();
            
            Log::info('Due bills processing completed', [
                'processed_count' => $processed
            ]);
            
        } catch (\Exception $e) {
            Log::error('Due bills processing failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            throw $e;
        }
    }
}