<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\ApplicationService;
use App\Models\ApplicationWorkflow;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_study_services_use_configured_rates_and_are_scoped_to_the_application(): void
    {
        $admin = User::factory()->create(['account_type' => 'Admin']);
        $study = Application::create([
            'category' => 'Study', 'primary_counsellor_id' => $admin->id,
            'organisation_id' => Organisation::create(['name' => 'Test organisation'])->id,
        ]);
        $otherStudy = Application::create(['category' => 'Study', 'primary_counsellor_id' => $admin->id]);
        $visa = Application::create(['category' => 'Visa', 'primary_counsellor_id' => $admin->id]);
        $workflow = ApplicationWorkflow::create(['category' => 'Visa', 'name' => '500 - Student Visa']);
        $workflow->visa_charges = [
            ['effective' => today()->subDay()->toDateString(), 'primary' => '2500.00', 'adult' => '1530.00'],
            ['effective' => today()->addDay()->toDateString(), 'primary' => '3000.00'],
        ];
        $workflow->save();

        $this->actingAs($admin);
        $store = route('applications.services.store', $study);
        $this->postJson($store, [
            'type' => 'visa', 'option' => array_key_first(ApplicationService::visaOptions()),
            'quantities' => ['adult' => 1], 'total_cents' => 1,
        ])->assertOk();
        $service = $study->services()->sole();
        $this->assertSame(403000, $service->total_cents);
        $this->patchJson(route('applications.services.update', [$otherStudy, $service]), ['type' => 'gsr'])->assertNotFound();
        $this->postJson(route('applications.services.store', $visa), ['type' => 'gsr'])->assertNotFound();
        $this->get(route('applications.show', ['application' => $study, 'tab' => 'services']))
            ->assertOk()->assertSee('A$ 4,030.00');

        $this->postJson($store, ['type' => 'gsr'])->assertOk();
        $this->postJson($store, ['type' => 'gsr'])->assertUnprocessable();
        $this->deleteJson(route('applications.services.destroy', [$study, $service]))->assertOk();
        $this->assertDatabaseMissing('application_services', ['id' => $service->id]);
    }

    public function test_oshc_screenshot_replacement_keeps_the_file_private(): void
    {
        Storage::fake('local');
        $admin = User::factory()->create(['account_type' => 'Admin']);
        $study = Application::create(['category' => 'Study', 'primary_counsellor_id' => $admin->id]);
        $fields = [
            'type' => 'oshc', 'provider' => 'Allianz Care', 'cover' => 'Single',
            'starts' => '2027-02-05', 'ends' => '2027-06-05', 'amount' => '171.25',
        ];

        $this->actingAs($admin)->post(route('applications.services.store', $study),
            $fields + ['screenshot' => UploadedFile::fake()->image('cover.png')])->assertOk();
        $service = $study->services()->sole();
        $original = $service->screenshot;
        Storage::disk('local')->assertExists($original);

        $this->patch(route('applications.services.update', [$study, $service]), $fields)->assertOk();
        $this->assertSame($original, $service->refresh()->screenshot);
        $this->patch(route('applications.services.update', [$study, $service]),
            $fields + ['screenshot' => UploadedFile::fake()->image('replacement.png')])->assertOk();
        Storage::disk('local')->assertMissing($original)->assertExists($service->refresh()->screenshot);
        $url = route('applications.services.screenshot', [$study, $service]);
        $this->get($url)->assertDownload();
        auth()->logout();
        $this->get($url)->assertRedirect(route('login'));
    }
}
