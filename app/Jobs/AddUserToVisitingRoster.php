<?php

namespace App\Jobs;

use Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class AddUserToVisitingRoster implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $userId)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // ex: https://api.vatusa.net/v2/facility/{id}/roster/manageVisitor/{cid}
        $URL = config('app.vatusa_api_url').'/v2/facility/'.config('app.vatusa_facility').'/roster/manageVisitor/'.$this->userId;

        $request = Http::post($URL, [
            'apikey' => config('app.vatusa_api_key'),
        ]);

        if ($request->failed()) {
            Log::error('Failed to add user to the VATUSA visiting roster', [
                'user_id' => $this->userId,
                'status' => $request->status(),
                'body' => $request->body(),
                'endpoint' => $URL,
            ]);
        } else {
            Log::info('Added user to the VATUSA visiting roster', [
                'user_id' => $this->userId,
            ]);
        }
    }
}
