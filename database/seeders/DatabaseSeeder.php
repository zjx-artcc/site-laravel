<?php

namespace Database\Seeders;

use App;
use App\Jobs\SyncRoster;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(
        PermissionSeeder $permissionSeeder,
        UserSeeder $userSeeder,
        StatisticsPrefixesSeeder $statisticsPrefixes,
        VatsimStatisticsSyncSeeder $vatsimStatisticsSyncSeeder,
    ): void {
        $permissionSeeder->run();
        $userSeeder->run();
        $statisticsPrefixes->run();

        if (App::environment() === 'development') {
            SyncRoster::dispatch();
            $vatsimStatisticsSyncSeeder->run();
        }
    }
}
