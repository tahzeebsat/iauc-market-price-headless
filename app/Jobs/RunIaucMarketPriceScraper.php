<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use App\Models\IaucModel;

class RunIaucMarketPriceScraper implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Artisan::call('dusk', ['--filter' => 'IaucLoginTest']);

        $scrappingModel = IaucModel::leftJoin('iauc_market_price_logs', 'iauc_models.id', '=', 'iauc_market_price_logs.model_id')
            ->whereNull('iauc_market_price_logs.id')
            ->first();

        if ($scrappingModel) {
            dispatch(new self());
        }

    }
}
