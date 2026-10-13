<?php

namespace App\Jobs;

use App\Enums\LoaStatus;
use App\Mail\LoaExpired;
use App\Models\Loa;
use App\Models\Staff;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ExpireLoas implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $expiredLoas = Loa::where('status', LoaStatus::APPROVED)
            ->where('end_date', '<', now()->toDateString())
            ->get();

        $staffToNotify = Staff::whereIn('title_short', ['ATM', 'DATM'])
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter();

        foreach ($expiredLoas as $loa) {
            $loa->status = LoaStatus::INACTIVE;
            $loa->save();

            Mail::to($loa->user->email)->bcc($staffToNotify)->queue(new LoaExpired($loa));
            Log::info('LOA #'.$loa->id.' for user '.$loa->user_id.' expired.');
        }
    }
}
