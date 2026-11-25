<?php

namespace Tests\Feature;

use App\Http\Controllers\VisaChargeConfigurationController;
use App\Models\ApplicationWorkflow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VisaChargeConfigurationTest extends TestCase
{
    use RefreshDatabase;

    public function test_visa_charge_revisions_validate_dates_and_resolve_available_prices(): void
    {
        $admin = User::factory()->create(['account_type' => User::ACCOUNT_TYPE_ADMIN]);
        $visa = ApplicationWorkflow::create(['category' => 'Visa', 'name' => '500 Student Visa']);
        ApplicationWorkflow::create(['category' => 'Study', 'name' => 'Higher Education']);

        $this->actingAs($admin)->withoutVite()
            ->get(route('configuration', ['tab' => 'visa-charges']))
            ->assertOk()->assertSee('Visa Application Charges')->assertSee('500 Student Visa')->assertDontSee('Higher Education');

        $url = route('configuration.visa-charges.update');
        $group = ['id' => $visa->id, 'revision' => VisaChargeConfigurationController::revision($visa)];
        $save = fn (array $rows) => $this->patchJson($url, ['groups' => [$group + ['rows' => $rows]]]);
        $save([['name' => 'Standard', 'primary' => 100]])
            ->assertUnprocessable()->assertJsonValidationErrors('groups.0.rows.0.effective');
        $save([
            ['name' => 'Standard', 'effective' => '2026-07-01', 'primary' => 100],
            ['name' => 'standard', 'effective' => '2026-07-01', 'primary' => 200],
        ])->assertUnprocessable()->assertJsonValidationErrors('groups.0.rows.1.effective');
        $rows = [
            ['name' => 'Standard', 'effective' => '2026-07-01', 'primary' => 100],
            ['name' => 'Standard', 'effective' => '2026-08-01', 'primary' => 200],
            ['name' => 'Standard', 'effective' => '2026-09-01', 'primary' => 0],
            ['name' => 'ASEAN', 'effective' => '2026-07-01', 'primary' => 50],
        ];
        $save($rows)->assertOk();
        $save($rows)->assertUnprocessable()->assertJsonValidationErrors('groups');

        $visa->refresh();
        $this->assertSame(['ASEAN', 'Standard'], array_column($visa->visaChargesAsOf('2026-08-01'), 'name'));
        $this->assertSame(['50.00', '200.00'], array_column($visa->visaChargesAsOf('2026-08-01'), 'primary'));
        $this->assertSame(['ASEAN'], array_column($visa->visaChargesAsOf('2026-09-01'), 'name'));
    }
}
