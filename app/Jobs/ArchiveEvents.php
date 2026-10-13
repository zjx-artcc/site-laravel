<?php

namespace App\Jobs;

use App\Models\Event;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ArchiveEvents implements ShouldQueue
{
    use Queueable;

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
        Event::query()
            ->whereNull('archived_at')
            ->where('end', '<=', now()->subHours(24))
            ->chunkById(100, function ($events) {

                foreach ($events as $event) {
                    $event->update([
                        'archived' => true,
                        'hidden' => true,
                        'archived_at' => now(),
                    ]);
                }

            });
    }
}
