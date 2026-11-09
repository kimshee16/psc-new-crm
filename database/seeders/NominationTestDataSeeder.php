<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\NominationSubclass;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NominationTestDataSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException('Nomination UAT data is intended for local and testing environments only.');
        }

        $owner = User::query()->where('email', 'admin@psccrm.test')->where('is_active', true)->first();
        if (! $owner) {
            throw new RuntimeException('The active admin@psccrm.test account must exist before seeding nomination UAT data.');
        }

        DB::transaction(function () use ($owner) {
            $visaOptions = NominationSubclass::visaOptions();
            foreach (['482' => true, '186' => false, '494' => true, '491' => false] as $code => $required) {
                if (! isset($visaOptions[$code])) {
                    throw new RuntimeException('Configure a full visa name for subclass '.$code.' before seeding nomination UAT data.');
                }
                NominationSubclass::firstOrCreate(['subclass' => (string) $code], ['sbs_required' => $required]);
            }

            $clients = [];
            foreach ([
                [910074, 'Ana', 'Maria', 'Santos', 'nomination.ana@example.test'],
                [910170, 'Daniel', 'Min', 'Park', 'nomination.daniel@example.test'],
            ] as [$publicId, $firstName, $middleName, $surname, $email]) {
                $existing = Client::query()->where('client_id', $publicId)->first();
                if ($existing && $existing->email !== $email) {
                    throw new RuntimeException('Client ID '.$publicId.' is already in use by another record. No UAT data was changed.');
                }
                $clients[] = Client::firstOrCreate(['client_id' => $publicId], [
                    'created_by_user_id' => $owner->id, 'first_name' => $firstName,
                    'middle_name' => $middleName, 'surname' => $surname, 'email' => $email,
                    'mobile' => '', 'nationality' => 'Philippine', 'current_location' => 'Melbourne',
                    'admin_office' => 'Melbourne', 'primary_counsellor' => $owner->name,
                    'client_status' => 'Active', 'tag' => 'Visa',
                    'notes' => 'Synthetic nominee for CV2P-74 / CV2P-170 user acceptance testing.',
                ]);
            }

            $scenarios = [
                ['No SBS', null, '482', 'Morgan Lee'],
                ['Active SBS', 'Active', '186', 'Taylor Quinn'],
                ['Expired SBS', 'Expired', '494', 'Casey Brooks'],
                ['SBS Not Started', 'Not Started', '491', 'Riley Jordan'],
            ];
            foreach ($scenarios as $index => [$scenario, $sbsStatus, $code, $externalName]) {
                $organisation = Organisation::firstOrCreate([
                    'name' => '[UAT Nomination] '.$scenario.' Pty Ltd', 'created_by_user_id' => $owner->id,
                ], [
                    'trading_name' => 'Nomination Test - '.$scenario,
                    'address' => 'Synthetic organisation for CV2P-74 / CV2P-170 testing.',
                ]);

                if ($sbsStatus !== null) {
                    $organisation->applications()->firstOrCreate([
                        'category' => 'SBS', 'notes' => 'UAT CV2P-74/170: '.$scenario,
                    ], [
                        'sponsor_type' => 'Standard Business Sponsor', 'status' => $sbsStatus,
                        'primary_counsellor_id' => $owner->id,
                        'approval_date' => $sbsStatus === 'Not Started' ? null : now()->subYears(2)->toDateString(),
                        'expiry_date' => match ($sbsStatus) {
                            'Active' => now()->addYear()->toDateString(),
                            'Expired' => now()->subYear()->toDateString(),
                            default => null,
                        },
                        'trn' => 'UAT000000000'.($index + 1),
                    ]);
                }

                foreach (['internal', 'external'] as $mode) {
                    $organisation->applications()->firstOrCreate([
                        'category' => 'Nomination', 'notes' => 'UAT CV2P-74/170: '.$code.' '.$mode,
                    ], [
                        'nomination_pathway' => $visaOptions[$code],
                        'nominee_client_id' => $mode === 'internal' ? $clients[$index % 2]->id : null,
                        'nominee_name' => $mode === 'external' ? $externalName : null,
                        'primary_counsellor_id' => $owner->id, 'status' => 'Not Started', 'priority' => 'M',
                        'nominated_position_title' => 'UAT sample position',
                    ]);
                }
            }
        });

        $this->command?->info('Nomination UAT data ready for '.$owner->email.': 2 clients, 4 organisations, 3 SBS records and 8 nominations.');
    }
}
