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

        $onlineData = Http::get($API_ENDPOINT);

        // Checked before truncating: previously a failed request still emptied
        // the table, so any VATSIM blip showed the facility as fully offline.
        if ($onlineData->failed()) {
            Log::error('Online controller sync failed', [
                'status' => $onlineData->status(),
                'endpoint' => $API_ENDPOINT,
            ]);

            return;
        }

        $controllers = $onlineData->json();

        if (! is_array($controllers)) {
            Log::error('Online controller sync got a non-array payload', ['endpoint' => $API_ENDPOINT]);

            return;
        }

        $prefixes = StatisticsPrefixes::pluck('name')->toArray();
        OnlineController::truncate();

        $facilityCount = 0;

        foreach ($controllers as $controller) {
            $onlineController = new OnlineControllerDTO($controller);
            if (Str::startsWith($onlineController->callsign, $prefixes)) {
                OnlineController::fromDTO($onlineController);
                $facilityCount++;
                Log::debug('Facility controller online', ['callsign' => $onlineController->callsign]);
            }
        }

        // Debug rather than info: this job runs every minute, so an info line
        // per run would add ~1,400 lines a day saying nothing changed.
        Log::debug('Online controller sync complete', [
            'network_controllers' => count($controllers),
            'facility_controllers' => $facilityCount,
        ]);
    }
}
