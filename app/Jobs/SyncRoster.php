<?php

namespace App\Jobs;

use App;
use App\DTOs\VatusaFacilityInfoDTO;
use App\DTOs\VatusaRosterUser;
use App\Models\Staff;
use App\Models\User;
use Http;
use Illuminate\Contracts\Broadcasting\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncRoster implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct() {}

    private function updateRoster()
    {
        $ROSTER_API_ENDPOINT = config('app.vatusa_api_url').'/v2/facility/'.config('app.vatusa_facility').'/roster/both';

        Log::debug('Fetching roster from VATUSA', ['endpoint' => $ROSTER_API_ENDPOINT]);

        $rosterData = Http::get($ROSTER_API_ENDPOINT, [
            'apikey' => config('app.vatusa_api_key'),
        ]);

        if ($rosterData->failed()) {
            throw new \Exception('Failed to fetch roster data: '.$rosterData->status().' - '.$rosterData->body());
        }

        $roster = $rosterData->json();

        // Snapshot membership so joiners and leavers can be reported.
        $before = User::where('rostered', true)->pluck('id')->all();

        User::where(['rostered' => true])->update(['rostered' => false]);

        for ($i = 0; $i < count($roster['data']); $i++) {
            $vatusaUser = new VatusaRosterUser($roster['data'][$i]);

            User::updateFromVatusa($vatusaUser);
        }

        $after = User::where('rostered', true)->pluck('id')->all();
        $joined = array_values(array_diff($after, $before));
        $departed = array_values(array_diff($before, $after));

        Log::debug('Roster membership diff', [
            'roster_size' => count($roster['data']),
            'joined_cids' => $joined,
            'departed_cids' => $departed,
        ]);

        foreach ($departed as $cid) {
            Log::info('Controller removed from roster', [
                'cid' => $cid,
                'reason' => 'no longer present on the VATUSA roster',
            ]);
        }

        foreach ($joined as $cid) {
            Log::info('Controller added to roster', ['cid' => $cid]);
        }

        // Clear hanging OIs
        User::where([
            'rostered' => false,
        ])->update([
            'operating_initials' => null,
        ]);
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

        foreach ($staffMembers as $staff) {
            $user = $staff->user;

            if (! $user) {
                Log::warning('Staff position references a user not on the roster', [
                    'cid' => $staff->user_id,
                    'title' => $staff->title_short,
                ]);
            }

            Log::debug('Assigning staff roles', [
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

        Log::debug('Fetching facility info from VATUSA', ['endpoint' => $FACILITY_INFO_ENDPOINT]);

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

            Log::info('Roster sync complete', [
                'rostered_controllers' => User::where('rostered', true)->count(),
                'staff_positions' => Staff::count(),
            ]);
        } catch (\Exception $e) {
            // Log error
            Log::error('Error syncing roster: '.$e->getMessage().'\n'.$e->getTraceAsString(), [
                'url' => config('app.vatusa_api_url').'/v2/facility/'.config('app.vatusa_facility'),
                'environment' => App::environment(),
                'exception' => get_class($e),
            ]);
        }
    }
}
