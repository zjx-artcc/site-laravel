<?php

namespace App\Jobs;

use App;
use App\DTOs\VatusaFacilityInfoDTO;
use App\DTOs\VatusaRosterUser;
use App\Jobs\Concerns\LogsJobRun;
use App\Models\Staff;
use App\Models\User;
use App\Support\PrivilegedAction;
use Http;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncRoster implements ShouldBeUnique, ShouldQueue
{
    use LogsJobRun, Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    private function updateRoster()
    {
        $ROSTER_API_ENDPOINT = config('app.vatusa_api_url').'/v2/facility/'.config('app.vatusa_facility').'/roster/both';

        $this->runDebug('fetching roster from VATUSA', ['endpoint' => $ROSTER_API_ENDPOINT]);

        $rosterData = Http::get($ROSTER_API_ENDPOINT, [
            'apikey' => config('app.vatusa_api_key'),
        ]);

        if ($rosterData->failed()) {
            throw new \Exception('Failed to fetch roster data: '.$rosterData->status().' - '.$rosterData->body());
        }

        $roster = $rosterData->json();

        // Snapshot membership before the sync so we can report who joined and
        // who left, rather than just "the sync ran".
        $before = User::where('rostered', true)->pluck('id')->all();

        User::where(['rostered' => true])->update(['rostered' => false]);

        for ($i = 0; $i < count($roster['data']); $i++) {
            $vatusaUser = new VatusaRosterUser($roster['data'][$i]);

            User::updateFromVatusa($vatusaUser);
        }

        $after = User::where('rostered', true)->pluck('id')->all();

        $joined = array_values(array_diff($after, $before));
        $departed = array_values(array_diff($before, $after));

        $this->setMetric('roster_size_reported', count($roster['data']));
        $this->setMetric('roster_size_stored', count($after));
        $this->setMetric('controllers_joined', count($joined));
        $this->setMetric('controllers_departed', count($departed));

        $this->runDebug('roster membership diff', [
            'joined_cids' => $joined,
            'departed_cids' => $departed,
        ]);

        $this->recordRosterDepartures($departed);

        // Clear hanging OIs
        User::where([
            'rostered' => false,
        ])->update([
            'operating_initials' => null,
        ]);
    }

    /**
     * A controller dropping off the roster is a membership removal, so it goes
     * in the audit trail alongside staff-initiated removals — with no causer,
     * since VATUSA rather than a staff member made the call.
     *
     * @param  array<int, int>  $departedCids
     */
    private function recordRosterDepartures(array $departedCids): void
    {
        foreach (User::whereIn('id', $departedCids)->get() as $user) {
            PrivilegedAction::record(PrivilegedAction::ROSTER_USER_REMOVED, $user, [
                'cid' => $user->id,
                'name' => $user->name,
                'reason' => 'no longer present on the VATUSA roster',
            ]);
        }
    }

    private function syncRosteredRole()
    {
        $users = User::all();

        foreach ($users as $user) {
            if ($user->rostered) {
                $user->assignRole('rostered');
            } else {
                $user->removeRole('rostered');
            }
        }

        $this->setMetric('rostered_role_synced', $users->count());
        $this->runDebug('rostered role synced');
    }

    private function clearUserRoles()
    {
        $users = User::all();

        foreach ($users as $user) {
            $user->removeRole('staff', 'admin', 'training', 'events', 'facilities', 'instructor');
        }
    }

    private function assignRoles()
    {
        $staffMembers = Staff::all();

        $this->setMetric('staff_positions', $staffMembers->count());

        foreach ($staffMembers as $staff) {
            $user = $staff->user;

            if (! $user) {
                $this->runWarning('staff position references a user not on the roster', [
                    'title' => $staff->title_short,
                    'cid' => $staff->user_id,
                ]);
            }

            $this->runDebug('assigning staff roles', [
                'cid' => $staff->user_id,
                'title' => $staff->title_short,
            ]);

            switch ($staff->title_short) {
                case 'ATM':
                case 'DATM':
                    $user?->assignRole('admin', 'training', 'instructor', 'facilities', 'events', 'staff');
                    break;
                case 'TA':
                case 'ATA':
                    $user?->assignRole('admin', 'training', 'staff');
                    break;
                case 'WM':
                    $user?->assignRole('admin', 'training', 'instructor', 'facilities', 'events', 'staff');
                    break;
                case 'EC':
                    $user?->assignRole('events', 'staff');
                    break;
                case 'FE':
                    $user?->assignRole('facilities', 'staff');
                    break;
                case 'INS':
                    $user?->assignRole('instructor', 'training', 'staff');
                    break;
                case 'MTR':
                    $user?->assignRole('training', 'staff');
                    break;
            }
        }
    }

    private function updateStaffMembers()
    {
        $FACILITY_INFO_ENDPOINT = config('app.vatusa_api_url').'/v2/facility/'.config('app.vatusa_facility');
        $this->runDebug('fetching facility info from VATUSA', ['endpoint' => $FACILITY_INFO_ENDPOINT]);

        $facilityInfo = Http::get($FACILITY_INFO_ENDPOINT, [
            'apikey' => config('app.vatusa_api_key'),
        ]);

        if ($facilityInfo->failed()) {
            throw new \Exception('Failed to fetch facility info: '.$facilityInfo->status().' - '.$facilityInfo->body());
        }

        $this->clearUserRoles();
        Staff::truncate();

        $infoDTO = new VatusaFacilityInfoDTO($facilityInfo->json()['data']);

        Staff::fromFacilityInfoDTO($infoDTO);

        $this->assignRoles();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->startRun(['facility' => config('app.vatusa_facility')]);

        try {
            $this->updateRoster();

            $this->updateStaffMembers();

            if (App::environment() == 'development') {
                $testUsers = User::where([
                    'first_name' => 'Web',
                ])->get();

                foreach ($testUsers as $user) {
                    $user->assignRole('admin', 'staff', 'training', 'events', 'facilities', 'instructor');
                    $user->rostered = true;
                    $user->division = 'USA';
                    $user->facility = 'ZJX';
                    $user->save();
                }
            }

            $this->syncRosteredRole();

            $this->finishRun();
        } catch (\Exception $e) {
            $this->failRun($e, [
                'url' => config('app.vatusa_api_url').'/v2/facility/'.config('app.vatusa_facility'),
                'environment' => App::environment(),
            ]);
        }
    }
}
