<?php

use App\Enums\TrainingStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Bring `active` in line with `status` on existing assignments.
     *
     * The Edit page used to expose `active` as its own checkbox beside the
     * status dropdown, so the two could disagree. Both now derive `active` from
     * the status, but rows written under the old form keep whatever combination
     * was saved: an assignment marked Complete with Active still ticked goes on
     * blocking the student's next request, since create() refuses one while any
     * active assignment exists.
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

        DB::table('training_assignments')
            ->whereNotIn('status', $closed)
            ->where('active', false)
            ->update(['active' => true]);
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
