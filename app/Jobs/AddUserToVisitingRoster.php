<?php

namespace App\Jobs;

use App\Models\User;
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

        $user = User::find($this->userId);
        $activity = $user ? activity()->performedOn($user) : activity();

        if ($request->failed()) {
            Log::error('Failed to add user '.$this->userId.' to visiting roster. Response: '.$request->body());

            $activity->withProperties(['attributes' => [
                'user_id' => $this->userId,
                'status' => $request->status(),
            ]])->log('Failed to add user to visiting roster');
        } else {
            Log::info('Successfully added user '.$this->userId.' to visiting roster.');

            $activity->withProperties(['attributes' => [
                'user_id' => $this->userId,
            ]])->log('Added user to visiting roster');
        }
    }
}
