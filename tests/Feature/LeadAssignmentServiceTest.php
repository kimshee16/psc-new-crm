<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Support\PSC\LeadAssignmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadAssignmentServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_nationality_location_redirection_takes_priority_over_location_default(): void
    {
        $melbourneUser = User::factory()->create([
            'name' => 'Melbourne Counsellor',
            'offices' => ['MEL'],
        ]);
        $sydneyUser = User::factory()->create([
            'name' => 'Sydney Counsellor',
            'offices' => ['SYD'],
        ]);

        DB::table('lead_redirections')->insert([
            'nationality' => 'Philippines',
            'current_location' => 'Melbourne',
            'office_code' => 'SYD',
            'active' => true,
            'sort_order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $redirectedAssignment = app(LeadAssignmentService::class)->assign('Philippines', 'Melbourne');

        $this->assertSame($sydneyUser->id, $redirectedAssignment['user']?->id);
        $this->assertSame('SYD', $redirectedAssignment['office_code']);
        $this->assertStringContainsString('redirected to Sydney', $redirectedAssignment['reason']);

        $defaultAssignment = app(LeadAssignmentService::class)->assign('Australia', 'Melbourne');

        $this->assertSame($melbourneUser->id, $defaultAssignment['user']?->id);
        $this->assertSame('MEL', $defaultAssignment['office_code']);
    }

    public function test_round_robin_uses_next_eligible_default_office_user(): void
    {
        $firstUser = User::factory()->create([
            'name' => 'First Counsellor',
            'offices' => ['MEL'],
        ]);
        $secondUser = User::factory()->create([
            'name' => 'Second Counsellor',
            'offices' => ['MEL'],
        ]);
        User::factory()->create([
            'name' => 'Skipped Counsellor',
            'offices' => ['MEL'],
            'include_in_round_robin' => false,
        ]);
        $onLeaveUser = User::factory()->create([
            'name' => 'Leave Counsellor',
            'offices' => ['MEL'],
        ]);

        DB::table('staff_leaves')->insert([
            'user_id' => $onLeaveUser->id,
            'starts_on' => now()->subDay()->toDateString(),
            'ends_on' => now()->addDay()->toDateString(),
            'reason' => 'Annual leave',
            'active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->createAssignedLead($firstUser);

        $assignment = app(LeadAssignmentService::class)->assign('Australia', 'Melbourne');

        $this->assertSame($secondUser->id, $assignment['user']?->id);
        $this->assertSame('Second Counsellor', $assignment['name']);
        $this->assertSame('MEL', $assignment['office_code']);
    }

    private function createAssignedLead(User $user): Lead
    {
        $client = Client::create([
            'client_id' => 3001,
            'created_by_user_id' => $user->id,
            'first_name' => 'Round',
            'middle_name' => '',
            'surname' => 'Robin',
            'dob' => null,
            'phone_country_code' => '+61',
            'phone_number' => '412 555 000',
            'mobile' => '+61 412 555 000',
            'email' => 'round.robin@example.test',
            'nationality' => 'Australia',
            'current_location' => 'Melbourne',
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'admin_office' => 'Melbourne',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'passport_photo_path' => null,
            'notes' => '',
            'primary_counsellor' => $user->name,
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Web',
        ]);

        return Lead::create([
            'lead_id' => 3001,
            'client_id' => $client->id,
            'assigned_user_id' => $user->id,
            'assigned_name' => $user->name,
            'assigned_office_code' => 'MEL',
            'assigned_office' => 'Melbourne',
            'assignment_reason' => 'Previous round robin assignment',
            'form_type' => 'contact_us',
            'source' => 'Contact us',
            'status' => 'New',
            'priority' => 'medium',
            'prospective_service' => 'Study',
            'first_name' => 'Round',
            'middle_name' => '',
            'surname' => 'Robin',
            'dob' => null,
            'email' => 'round.robin@example.test',
            'phone_country_code' => '+61',
            'phone_number' => '412 555 000',
            'mobile' => '+61 412 555 000',
            'nationality' => 'Australia',
            'current_location' => 'Melbourne',
            'highest_education_level' => '',
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'enquiry' => '',
            'notes' => '',
            'submitted_at' => now()->subMinute(),
        ]);
    }
}
