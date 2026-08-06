<?php

namespace App\Jobs;

use App\DTOs\OnlineControllerDTO;
use App\Jobs\Concerns\LogsJobRun;
use App\Models\OnlineController;
use App\Models\StatisticsPrefixes;
use Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Str;

class UpdateOnlineControllers implements ShouldQueue
{
    use LogsJobRun, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    /**
     * This job runs every minute, so a per-run info line would be ~1,400 lines
     * a day of "nothing happened". The summary drops to debug; anything that
     * actually goes wrong is still warned or errored at full volume.
     */
    protected function jobRunCompletionLevel(): string
    {
        return 'debug';
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $API_ENDPOINT = config('app.vatsim_api_url').'/v2/atc/online';

        $this->startRun();

        $onlineData = Http::get($API_ENDPOINT);

        if ($onlineData->failed()) {
            $this->abortRun('VATSIM online-controller request failed', [
                'endpoint' => $API_ENDPOINT,
                'status' => $onlineData->status(),
                'body' => Str::limit($onlineData->body(), 500),
            ]);

            return;
        }

        $controllers = $onlineData->json();

        if (! is_array($controllers)) {
            $this->abortRun('VATSIM returned a non-array payload', [
                'endpoint' => $API_ENDPOINT,
                'body' => Str::limit($onlineData->body(), 500),
            ]);

            return;
        }

        $prefixes = StatisticsPrefixes::pluck('name')->toArray();
        OnlineController::truncate();

        $this->setMetric('controllers_online_network', count($controllers));

        foreach ($controllers as $controller) {
            $onlineController = new OnlineControllerDTO($controller);
            if (Str::startsWith($onlineController->callsign, $prefixes)) {
                OnlineController::fromDTO($onlineController);
                $this->countMetric('controllers_online_facility');
                $this->runDebug('facility controller online', ['callsign' => $onlineController->callsign]);
            }
        }

        $this->finishRun();
    }
}
