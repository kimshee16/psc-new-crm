<?php

namespace Database\Seeders;

use App\Models\User;
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
    }
}
