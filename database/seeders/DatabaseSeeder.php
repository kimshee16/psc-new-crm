<?php

namespace Database\Seeders;

use App\Models\Location;
use App\Models\User;
use App\Support\PSC\WebsiteLeadReferences;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'admin@psccrm.test'],
            [
                'name' => 'PSC Admin',
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'offices' => ['MEL', 'SYD', 'BNE', 'PER', 'ADL'],
                'password' => Hash::make('password'),
            ]
        );

        foreach (WebsiteLeadReferences::defaultLocations() as $index => $location) {
            Location::updateOrCreate(
                ['name' => $location['name']],
                [
                    'office_code' => $location['office_code'],
                    'active' => true,
                    'sort_order' => $index + 1,
                ]
            );
        }
    }
}
