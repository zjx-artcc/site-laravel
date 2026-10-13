<?php

namespace App\Jobs;

use App\DTOs\OnlineControllerDTO;
use App\Models\OnlineController;
use App\Models\StatisticsPrefixes;
use Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Str;

class UpdateOnlineControllers implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $API_ENDPOINT = config('app.vatsim_api_url').'/v2/atc/online';

        try {
            $onlineData = Http::retry(2, 500)->timeout(20)->get($API_ENDPOINT);
        } catch (\Exception $e) {
            Log::error('Failed to fetch VATSIM online controllers: '.$e->getMessage());

            activity()->withProperties(['attributes' => [
                'endpoint' => $API_ENDPOINT,
                'exception' => $e->getMessage(),
            ]])->log('Online controller sync failed');

            return;
        }

        $controllers = $onlineData->json();

        // Checked before truncating: a failed request used to empty the table
        // anyway, showing the facility as fully offline on any VATSIM blip.
        if ($onlineData->failed() || ! is_array($controllers)) {
            Log::error('Online controller sync failed', [
                'status' => $onlineData->status(),
                'endpoint' => $API_ENDPOINT,
            ]);

            activity()->withProperties(['attributes' => [
                'status' => $onlineData->status(),
                'endpoint' => $API_ENDPOINT,
            ]])->log('Online controller sync failed');

            return;
        }

        $prefixes = StatisticsPrefixes::pluck('name')->toArray();
        OnlineController::truncate();

        foreach ($controllers as $controller) {
            $onlineController = new OnlineControllerDTO($controller);
            if (Str::startsWith($onlineController->callsign, $prefixes)) {
                OnlineController::fromDTO($onlineController);
            }
        }
    }
}
