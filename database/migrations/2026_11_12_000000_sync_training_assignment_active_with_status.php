<?php

use App\Enums\TrainingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Deactivate existing assignments whose status says they are finished.
     *
     * The Edit page used to expose `active` as its own checkbox beside the
     * status dropdown, so the two could disagree. Both now derive `active` from
     * the status, but rows written under the old form keep whatever combination
     * was saved: an assignment marked Complete with Active still ticked goes on
     * blocking the student's next request, since create() refuses one while any
     * active assignment exists.
     *
     * Only this direction is normalised. The mirror case — an open status with
     * `active = false` — is left alone on purpose, because staff could have set
     * it deliberately to park a request without marking it Forfeit. Waking those
     * rows up could put a second live request in the queue for a student who has
     * since re-requested, and would quietly exempt a dormant observer from the
     * roster purge (RosterPurgeController builds its exemption list from
     * `active = true`). They heal the next time someone sets the row's status
     * instead, which is a deliberate act with an activity-log entry behind it.
     */
    public function up(): void
    {
        $closed = array_map(
            fn (TrainingStatus $status) => $status->value,
            TrainingStatus::closed()
        );

        DB::table('training_assignments')
            ->whereIn('status', $closed)
            ->where('active', true)
            ->update(['active' => false]);
    }

    /**
     * Irreversible: the pre-migration values are not recorded anywhere, and the
     * app no longer has a way to produce them.
     */
    public function down(): void
    {
        //
    }
};
