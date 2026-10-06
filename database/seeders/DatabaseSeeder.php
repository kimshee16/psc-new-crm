<?php

namespace Database\Seeders;

use App\Models\Client;
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
        $admin = User::updateOrCreate(
            ['email' => 'admin@psccrm.test'],
            [
                'name' => 'PSC Admin',
                'account_type' => User::ACCOUNT_TYPE_ADMIN,
                'offices' => ['MEL', 'SYD', 'BNE', 'PER', 'ADL'],
                'password' => Hash::make('password'),
            ]
        );

        $this->seedDummyClients($admin);

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

    private function seedDummyClients(User $admin): void
    {
        $currentUser = $admin->name;
        $otherCounsellor = 'Priya Raman';
        $otherAgent = 'Nina Patel';
        $now = now();

        $clients = [
            ['client_id' => 1001, 'first_name' => 'Ana', 'middle_name' => 'Maria', 'surname' => 'Santos', 'mobile' => '+61 412 345 001', 'client_status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => $otherCounsellor, 'migration_agent' => $otherAgent],
            ['client_id' => 1002, 'first_name' => 'Daniel', 'middle_name' => 'Min', 'surname' => 'Park', 'mobile' => '+61 412 345 002', 'client_status' => 'Prospect', 'tag' => 'Visa', 'primary_counsellor' => $otherCounsellor, 'secondary_counsellor' => $currentUser, 'migration_agent' => $otherAgent],
            ['client_id' => 1003, 'first_name' => 'Maria', 'middle_name' => 'Luisa', 'surname' => 'Cruz', 'mobile' => '+61 412 345 003', 'client_status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => $otherCounsellor, 'secondary_counsellor' => 'Liam OConnor', 'migration_agent' => $currentUser],
            ['client_id' => 1004, 'first_name' => 'Ravi', 'middle_name' => 'Arun', 'surname' => 'Kumar', 'mobile' => '+61 412 345 004', 'client_status' => 'On hold', 'tag' => 'PR', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => 'Marco Silva', 'migration_agent' => $otherAgent],
            ['client_id' => 1005, 'first_name' => 'Lucia', 'middle_name' => 'Elena', 'surname' => 'Herrera', 'mobile' => '+61 412 345 005', 'client_status' => 'Inactive', 'tag' => 'Visa', 'primary_counsellor' => 'Sofia Reyes', 'secondary_counsellor' => $currentUser, 'migration_agent' => 'Marco Silva'],
            ['client_id' => 1006, 'first_name' => 'Chen', 'middle_name' => 'Wei', 'surname' => 'Lin', 'mobile' => '+61 412 345 006', 'client_status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => '', 'migration_agent' => $otherAgent],
            ['client_id' => 1007, 'first_name' => 'Isabella', 'middle_name' => 'Rosa', 'surname' => 'Rossi', 'mobile' => '+61 412 345 007', 'client_status' => 'Archived', 'tag' => 'PR', 'primary_counsellor' => 'Priya Raman', 'secondary_counsellor' => 'Liam OConnor', 'migration_agent' => $currentUser],
            ['client_id' => 1008, 'first_name' => 'Mateo', 'middle_name' => 'Jose', 'surname' => 'Garcia', 'mobile' => '+61 412 345 008', 'client_status' => 'Prospect', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => 'Sofia Reyes', 'migration_agent' => $otherAgent],
            ['client_id' => 1009, 'first_name' => 'Aisha', 'middle_name' => 'Noor', 'surname' => 'Khan', 'mobile' => '+61 412 345 009', 'client_status' => 'Active', 'tag' => 'Visa', 'primary_counsellor' => $otherCounsellor, 'secondary_counsellor' => $currentUser, 'migration_agent' => $otherAgent],
            ['client_id' => 1010, 'first_name' => 'Paulo', 'middle_name' => 'Andre', 'surname' => 'Mendes', 'mobile' => '+61 412 345 010', 'client_status' => 'On hold', 'tag' => 'Student', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => '', 'migration_agent' => 'Marco Silva'],
            ['client_id' => 1011, 'first_name' => 'Mei', 'middle_name' => 'Ling', 'surname' => 'Zhang', 'mobile' => '+61 412 345 011', 'client_status' => 'Active', 'tag' => 'PR', 'primary_counsellor' => 'Sofia Reyes', 'secondary_counsellor' => 'Priya Raman', 'migration_agent' => $currentUser],
            ['client_id' => 1012, 'first_name' => 'Omar', 'middle_name' => 'Hassan', 'surname' => 'Ali', 'mobile' => '+61 412 345 012', 'client_status' => 'Inactive', 'tag' => 'Visa', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => $otherCounsellor, 'migration_agent' => $otherAgent],
            ['client_id' => 1013, 'first_name' => 'Grace', 'middle_name' => 'Anne', 'surname' => 'Taylor', 'mobile' => '+61 412 345 013', 'client_status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => 'Liam OConnor', 'secondary_counsellor' => $currentUser, 'migration_agent' => 'Marco Silva'],
            ['client_id' => 1014, 'first_name' => 'Hiro', 'middle_name' => 'Kenji', 'surname' => 'Tanaka', 'mobile' => '+61 412 345 014', 'client_status' => 'Prospect', 'tag' => 'Visa', 'primary_counsellor' => $currentUser, 'secondary_counsellor' => '', 'migration_agent' => $otherAgent],
            ['client_id' => 1015, 'first_name' => 'Fatima', 'middle_name' => 'Zahra', 'surname' => 'Rahman', 'mobile' => '+61 412 345 015', 'client_status' => 'Active', 'tag' => 'PR', 'primary_counsellor' => 'Liam OConnor', 'secondary_counsellor' => 'Sofia Reyes', 'migration_agent' => $currentUser],
            ['client_id' => 1016, 'first_name' => 'Noah', 'middle_name' => 'James', 'surname' => 'Wilson', 'mobile' => '+61 412 345 016', 'client_status' => 'Active', 'tag' => 'Student', 'primary_counsellor' => 'Priya Raman', 'secondary_counsellor' => 'Marco Silva', 'migration_agent' => $otherAgent],
            ['client_id' => 1017, 'first_name' => 'Sofia', 'middle_name' => 'Camila', 'surname' => 'Torres', 'mobile' => '+61 412 345 017', 'client_status' => 'Archived', 'tag' => 'Visa', 'primary_counsellor' => 'Nina Patel', 'secondary_counsellor' => 'Liam OConnor', 'migration_agent' => 'Marco Silva'],
        ];

        foreach ($clients as $client) {
            $emailFirst = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $client['first_name']));
            $emailSurname = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $client['surname']));
            $tag = $client['tag'];
            [$phoneCountryCode, $phoneNumber] = $this->phonePartsFromMobile($client['mobile']);

            Client::updateOrCreate(
                ['client_id' => $client['client_id']],
                $client + [
                    'created_by_user_id' => $admin->getKey(),
                    'dob' => '2000-04-12',
                    'phone_country_code' => $phoneCountryCode,
                    'phone_number' => $phoneNumber,
                    'email' => trim($emailFirst.'.'.$emailSurname, '.').'@example.test',
                    'nationality' => $tag === 'Student' ? 'Philippine' : 'Indian',
                    'current_location' => 'Melbourne',
                    'street' => '22 Collins Street',
                    'suburb' => 'Melbourne',
                    'state' => 'VIC',
                    'postcode' => '3000',
                    'overseas_address' => "Family residence\nHome country",
                    'admin_office' => 'Melbourne',
                    'current_visa' => $tag === 'Student' ? 'Student visa' : 'Visitor visa',
                    'visa_expiry' => '2026-10-25',
                    'passport_photo_path' => null,
                    'notes' => json_encode([
                        [
                            'body' => 'Sample note',
                            'author' => $currentUser,
                            'datetime' => $now->format('Y-m-d H:i:s'),
                        ],
                    ], JSON_THROW_ON_ERROR),
                ]
            );
        }
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function phonePartsFromMobile(string $mobile): array
    {
        if (preg_match('/^(\+\d{1,3})\s*(.+)$/', trim($mobile), $matches) === 1) {
            return [$matches[1], trim($matches[2])];
        }

        return ['+61', trim($mobile)];
    }
}
