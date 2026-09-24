<?php

namespace App\Jobs;

use App\Mail\ControllerRemovedFromRoster;
use App\Mail\RosterRemovalFailed;
use App\Models\User;
use Http;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class RemoveUserFromRoster implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $userId, public string $reason, public int $by)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $user = User::find($this->userId);

        if (! $user) {
            Log::warning('RemoveUserFromRoster: user '.$this->userId.' not found, skipping.');

            return;
        }

        $facility = config('app.vatusa_facility');
        $isVisitor = strcasecmp((string) $user->facility, (string) $facility) !== 0;

        // Visitors are managed through the manageVisitor endpoint; home controllers
        // are removed from the primary facility roster.
        $URL = $isVisitor
            ? config('app.vatusa_api_url').'/v2/facility/'.$facility.'/roster/manageVisitor/'.$this->userId
            : config('app.vatusa_api_url').'/v2/facility/'.$facility.'/roster/'.$this->userId;

        // VATUSA's roster-removal endpoint authenticates this server-to-server
        // call via API key rather than a staff session, so it requires the CID
        // of the responsible staff member explicitly. The visitor-removal
        // endpoint doesn't use "by", but harmlessly ignores it. VATUSA expects
        // the apikey as a query param and the rest as a form body, not JSON.
        $request = Http::asForm()
            ->withQueryParameters(['apikey' => config('app.vatusa_api_key')])
            ->delete($URL, [
                'reason' => $this->reason,
                'by' => $this->by,
            ]);

        // A 2xx response can still carry a JSON "error" field meaning the
        // removal didn't actually happen on VATUSA's side.
        $succeeded = $request->successful() && ! $request->json('error');

        $requestedBy = User::find($this->by);

        // Record the raw VATUSA request/response on the audit log so a failed
        // or disputed removal can be traced back to exactly what was sent.
        activity($isVisitor ? 'vatusa-visitor-removal' : 'vatusa-roster-removal')
            ->causedBy($requestedBy)
            ->performedOn($user)
            ->withProperties([
                'attributes' => [
                    'endpoint' => $URL,
                    'reason' => $this->reason,
                    'http_status' => $request->status(),
                    'response' => Str::limit($request->body(), 2000),
                ],
            ])
            ->event($succeeded ? 'roster-removal-succeeded' : 'roster-removal-failed')
            ->log($succeeded ? 'Removed from VATUSA roster' : 'VATUSA roster removal failed');

        if (! $succeeded) {
            Log::error('Failed to remove user '.$this->userId.' from roster. Response: '.$request->body());

            if ($requestedBy) {
                Mail::to($requestedBy->email)->queue(new RosterRemovalFailed($user, $this->reason, $request->body()));
            }

            return;
        }

        // Reflect the removal locally so the change is visible before the next
        // full roster sync runs.
        $user->rostered = false;
        $user->operating_initials = null;
        $user->save();

        Mail::to($user->email)->bcc(['atm@zjxartcc.org', 'datm@zjxartcc.org'])->queue(new ControllerRemovedFromRoster($user, $this->reason));

        Log::info('Successfully removed user '.$this->userId.' from roster. Reason: '.$this->reason);
    }
}
