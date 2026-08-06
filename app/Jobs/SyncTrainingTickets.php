<?php

namespace App\Jobs;

use App\Jobs\Concerns\LogsJobRun;
use App\Models\TrainingTicket;
use DateTime;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

class SyncTrainingTickets implements ShouldQueue
{
    use LogsJobRun, Queueable;

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
        // https://api.vatusa.net/v2/training/record/{recordID}
        $this->startRun();

        $unsyncedTickets = TrainingTicket::where(['vatusa_synced' => false])->get();

        $this->setMetric('tickets_pending', $unsyncedTickets->count());

        foreach ($unsyncedTickets as $ticket) {
            $this->createVatusaTrainingTicket($ticket);
        }

        $this->finishRun();
    }

    private function createVatusaTrainingTicket(mixed $ticket)
    {
        $API_URL = config('app.vatusa_api_url').'/v2/user/'.$ticket->user_id.'/training/record';

        try {
            $request = Http::post($API_URL, [
                'apikey' => config('app.vatusa_api_key'),
                'instructor_id' => $ticket->instructor_id,
                'session_date' => (new DateTime($ticket->session_start))->format('Y-m-d H:i'),
                'duration' => $ticket->duration,
                'position' => $ticket->position,
                'movements' => $ticket->movements,
                'score' => $ticket->score,
                'notes' => $ticket->notes,
                'location' => $ticket->location,
            ]);
        } catch (Exception $e) {
            $this->countMetric('tickets_errored');
            $this->failRun($e, ['ticket_id' => $ticket->id, 'user_id' => $ticket->user_id]);

            return;
        }

        if (! isset($request) || ! $request->successful()) {
            $this->countMetric('tickets_rejected');
            $this->runWarning('VATUSA rejected a training record', [
                'status' => isset($request) ? $request->status() : null,
                'body' => isset($request) ? $request->body() : null,
                'ticket_id' => $ticket->id,
                'user_id' => $ticket->user_id,
            ]);

            return;
        }

        $body = $request->json();

        $vatusaId = null;
        if (is_array($body)) {
            if (isset($body['data']['id'])) {
                $vatusaId = $body['data']['id'];
            } elseif (isset($body['data']['recordID'])) {
                $vatusaId = $body['data']['recordID'];
            } elseif (isset($body['data']['record']['id'])) {
                $vatusaId = $body['data']['record']['id'];
            } elseif (isset($body['recordID'])) {
                $vatusaId = $body['recordID'];
            } elseif (isset($body['id'])) {
                $vatusaId = $body['id'];
            }
        }

        $ticket->vatusa_synced = true;
        $ticket->vatusa_id = $vatusaId ? (string) $vatusaId : substr(preg_replace('/[^a-z0-9]/i', '', sha1($request->body() ?? (string) microtime(true))), 0, 12);
        $ticket->save();

        if (! $vatusaId) {
            $this->countMetric('tickets_synced_without_vatusa_id');
            $this->runWarning('VATUSA accepted a training record but returned no record ID', [
                'ticket_id' => $ticket->id,
                'fallback_vatusa_id' => $ticket->vatusa_id,
            ]);
        }

        $this->countMetric('tickets_synced');
        $this->runDebug('training record synced', [
            'ticket_id' => $ticket->id,
            'vatusa_id' => $ticket->vatusa_id,
        ]);
    }
}
