<?php

namespace App\Jobs;

use App\Models\TrainingTicket;
use DateTime;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SyncTrainingTickets implements ShouldQueue
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
        // https://api.vatusa.net/v2/training/record/{recordID}
        $unsyncedTickets = TrainingTicket::where(['vatusa_synced' => false]);

        foreach ($unsyncedTickets->get() as $ticket) {
            $this->createVatusaTrainingTicket($ticket);
        }
    }

    private function createVatusaTrainingTicket(mixed $ticket)
    {
        $API_URL = config('app.vatusa_api_url').'/v2/user/'.$ticket->user_id.'/training/record';

        // Append the "Certification Issued: …" line to the record notes sent to VATUSA.
        $certificationLabel = $ticket->certificationIssuedLabel();
        $notes = $ticket->notes.($certificationLabel ? "\n\n".$certificationLabel : '');

        // VATUSA has no no-show location, so send it as Classroom and flag it in the notes.
        if ($ticket->isNoShow()) {
            $notes = "No Show\n\n".$notes;
        }

        try {
            $request = Http::post($API_URL, [
                'apikey' => config('app.vatusa_api_key'),
                'instructor_id' => $ticket->instructor_id,
                'session_date' => (new DateTime($ticket->session_start))->format('Y-m-d H:i'),
                'duration' => $ticket->duration,
                'position' => $ticket->position,
                'movements' => $ticket->movements,
                'score' => $ticket->score,
                'notes' => $notes,
                'location' => $ticket->isNoShow() ? 0 : $ticket->location,
            ]);
        } catch (Exception $e) {
            Log::error($e->getMessage());

            return;
        }

        if (! isset($request) || ! $request->successful()) {
            Log::warning('Vatusa training record create failed', [
                'status' => isset($request) ? $request->status() : null,
                'body' => isset($request) ? $request->body() : null,
                'ticket_id' => $ticket->id,
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
    }
}
