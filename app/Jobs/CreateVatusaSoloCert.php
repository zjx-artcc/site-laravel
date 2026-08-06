<?php

namespace App\Jobs;

use App\Models\SoloCert;
use Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateVatusaSoloCert implements ShouldQueue
{
    use Queueable;

    private readonly string $API_URL;

    /**
     * Create a new job instance.
     */
    public function __construct(private readonly SoloCert $soloCert)
    {
        $this->API_URL = config('app.vatusa_api_url').'/v2/solo';
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $request = Http::post($this->API_URL, [
            'apikey' => config('app.vatusa_api_key'),
            'cid' => $this->soloCert->user_id,
            'position' => $this->soloCert->position,
            'expDate' => $this->soloCert->expires->format('Y-m-d'),
        ]);

        if ($request->failed()) {
            Log::error('Failed to push solo cert to VATUSA', [
                'solo_cert_id' => $this->soloCert->id,
                'user_id' => $this->soloCert->user_id,
                'position' => $this->soloCert->position,
                'status' => $request->status(),
                'body' => $request->body(),
            ]);
        } else {
            Log::info('Pushed solo cert to VATUSA', [
                'solo_cert_id' => $this->soloCert->id,
                'user_id' => $this->soloCert->user_id,
                'position' => $this->soloCert->position,
            ]);
        }
    }
}
