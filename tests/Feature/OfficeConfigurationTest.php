<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Office;
use App\Models\User;
use App\Support\PSC\LeadAssignmentService;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_offices_screen_contains_psc_ids_without_a_redundant_id_column(): void
    {
        $admin = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]);

        $this->actingAs($admin)->get('/configuration?tab=offices')
            ->assertOk()
            ->assertSee('Save Changes')
            ->assertSee('Remove Changes')
            ->assertSee('Office Code')
            ->assertSee('Default Office')
            ->assertSee('Office ID')
            ->assertSee('PHIL-ONSHORE')
            ->assertDontSee('>ID</th>', false);

        $this->assertSame([
            'MDN' => 109, 'VNO' => 108, 'LATAM' => 107, 'BRA' => 106,
            'PHIL-ONSHORE' => 105, 'MNL' => 104, 'SYD' => 103,
            'MEL' => 102, 'JKT' => 101, 'DPS' => 100,
        ], Office::query()->whereNotNull('office_id')->orderByDesc('office_id')->pluck('office_id', 'code')->all());
        $this->assertSame('MEL', Office::defaultCode());
    }

    public function test_saved_default_catches_all_other_and_unmapped_locations_and_allocates_work(): void
    {
        $admin = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN, 'include_in_round_robin' => false]);
        $counsellor = User::factory()->create(['offices' => ['MEL', 'SYD'], 'default_office_code' => 'SYD']);
        $payload = $this->payload('SYD');
        foreach ($payload['offices'] as &$office) {
            if ($office['code'] === 'SYD') {
                $office['sender_name'] = 'PSC Sydney';
                $office['sender_email'] = 'sydney@example.test';
            }
        }
        unset($office);

        $this->actingAs($admin)->patch('/configuration/offices', $payload)
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=offices');

        $this->assertSame(1, Office::query()->where('is_default', true)->count());
        $references = app(WebsiteLeadReferences::class);
        foreach (['Other Location', 'Australia - Other locations', 'Indonesia - Other locations', 'other', 'Unknown place', ''] as $location) {
            $this->assertSame('SYD', $references->officeCodeForLocation($location), $location);
        }
        $this->assertSame('MEL', $references->officeCodeForLocation('Australia - Melbourne'));
        $this->assertSame('JKT', $references->officeCodeForLocation('Indonesia - Jakarta'));
        $this->assertDatabaseHas('locations', ['name' => 'Other Location', 'office_code' => 'SYD']);
        $assignment = app(LeadAssignmentService::class)->assign('Australia', 'Other Location');
        $this->assertSame($counsellor->id, $assignment['user']?->id);
        $this->assertSame('SYD', $assignment['office_code']);
        $this->assertSame(['name' => 'PSC Sydney', 'address' => 'sydney@example.test'], $counsellor->officeSenderIdentity());

        $this->actingAs($admin)->get('/configuration?tab=offices')
            ->assertOk()->assertSee('sydney@example.test');
        $this->actingAs($admin)->get('/configuration?tab=locations')
            ->assertOk()->assertSee('SYD — Default Office');
    }

    public function test_invalid_or_incomplete_changes_are_rejected_without_partial_updates(): void
    {
        $admin = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]);
        $this->actingAs($admin);

        $payload = $this->payload('UNKNOWN');
        $this->patch('/configuration/offices', $payload)->assertSessionHasErrors('default_office_code');
        $payload = $this->payload('SYD');
        $payload['offices'][0]['sender_email'] = 'invalid-address';
        $this->patch('/configuration/offices', $payload)->assertSessionHasErrors('offices.0.sender_email');
        $payload = $this->payload('SYD');
        array_pop($payload['offices']);
        $this->patch('/configuration/offices', $payload)->assertSessionHasErrors('offices');
        $payload = $this->payload('SYD');
        $payload['offices'][0]['code'] = $payload['offices'][1]['code'];
        $this->patch('/configuration/offices', $payload)->assertSessionHasErrors('offices.0.code');
        $payload = $this->payload('SYD');
        unset($payload['default_office_code']);
        $this->patch('/configuration/offices', $payload)->assertSessionHasErrors('default_office_code');

        $this->assertSame('MEL', Office::defaultCode());
        $this->assertSame(1, Office::query()->where('is_default', true)->count());
        $this->assertSame(0, Office::query()->whereNotNull('sender_email')->count());
    }

    public function test_only_administrators_can_change_offices(): void
    {
        $this->patch('/configuration/offices', $this->payload())->assertRedirect('/login');
        foreach (['Counsellor', User::ACCOUNT_TYPE_MANAGER, User::ACCOUNT_TYPE_REGIONAL_MANAGER] as $role) {
            $user = User::factory()->create(['account_type' => $role]);
            $this->actingAs($user)->get('/configuration?tab=offices')->assertForbidden();
            $this->patch('/configuration/offices', $this->payload())->assertForbidden();
        }
        $admin = User::factory()->create(['account_type' => 'Super Admin']);
        $this->actingAs($admin)->patch('/configuration/offices', $this->payload('SYD'))
            ->assertSessionHasNoErrors()->assertRedirect('/configuration?tab=offices');
    }

    public function test_office_visibility_uses_configured_offices_without_granting_unassigned_access(): void
    {
        $manager = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_MANAGER, 'offices' => ['VNO', 'BRA']]);
        $this->actingAs($manager)->get('/my-offices/clients')->assertOk()
            ->assertViewHas('availableOffices', ['VNO' => 'Vietnam', 'BRA' => 'Brazil']);
        $this->get('/my-offices/clients?offices[]=MEL')->assertOk()
            ->assertViewHas('selectedOfficeCodes', ['VNO', 'BRA'])
            ->assertDontSee('Ana Santos');
    }

    public function test_other_location_cannot_override_global_default_and_sender_has_fallback(): void
    {
        $admin = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]);
        $location = Location::query()->where('name', 'Other Location')->firstOrFail();
        $this->actingAs($admin)->patch('/configuration/locations/'.$location->id, [
            'name' => 'Other Location', 'office_code' => 'SYD',
        ])->assertSessionHasNoErrors();
        $this->assertSame('MEL', $location->fresh()->office_code);
        $this->assertSame([
            'name' => config('mail.from.name'), 'address' => config('mail.from.address'),
        ], Office::defaultOffice()->senderIdentity());
    }

    private function payload(string $default = 'MEL'): array
    {
        return [
            'default_office_code' => $default,
            'offices' => Office::query()->orderBy('id')->get()->map(fn (Office $office) => [
                'code' => $office->code, 'sender_name' => null, 'sender_email' => null,
            ])->all(),
        ];
    }
}
