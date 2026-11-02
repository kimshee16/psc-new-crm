<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Support\PSC\RecordNotes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordNotesTest extends TestCase
{
    use RefreshDatabase;

    private function records(User $user): array
    {
        $client = Client::create([
            'client_id' => 8000 + Client::count(), 'first_name' => 'Notes', 'surname' => 'Client',
            'email' => 'notes@example.test', 'mobile' => '', 'nationality' => '', 'current_location' => '',
            'admin_office' => 'Melbourne', 'primary_counsellor' => $user->name,
        ]);
        $lead = Lead::create([
            'lead_id' => 9000 + Lead::count(), 'client_id' => $client->id, 'assigned_user_id' => $user->id,
            'assigned_name' => $user->name, 'assigned_office_code' => 'MEL', 'assigned_office' => 'Melbourne',
            'assignment_reason' => 'Test', 'form_type' => 'contact_us', 'source' => 'Contact us',
            'first_name' => 'Notes', 'email' => 'notes@example.test', 'phone_country_code' => '+61',
            'phone_number' => '', 'mobile' => '', 'nationality' => '', 'current_location' => '', 'submitted_at' => now(),
        ]);
        $application = Application::create(['client_id' => $client->id, 'category' => 'Study', 'primary_counsellor_id' => $user->id]);

        return ["/clients/{$client->client_id}/notes" => $client,
            "/leads/{$lead->lead_id}/notes" => $lead,
            "/applications/{$application->id}/notes" => $application];
    }

    public function test_all_modules_support_inline_notes_and_trusted_metadata_without_record_edit_mode(): void
    {
        $user = User::factory()->create(['account_type' => 'Counsellor']);
        $records = $this->records($user);
        $this->actingAs($user);
        foreach ($records as $url => $record) {
            $record->refresh();
            $record->update(['notes' => "Legacy first line\n\nLegacy second paragraph"]);
            $version = hash('sha256', $record->notes);
            $this->postJson($url, ['version' => $version, 'body' => "Newest\n<script>alert(1)</script>",
                'author' => 'Impersonated author', 'datetime' => '2000-01-01', 'status' => 'Finalised'])
                ->assertOk()->assertJsonPath('html', fn ($html) => str_contains($html, '&lt;script&gt;') && ! str_contains($html, '<script>alert(1)</script>'));
            $saved = app(RecordNotes::class)->all($record->fresh()->notes);
            $this->assertCount(2, $saved);
            $this->assertSame($user->name, $saved[0]['author']);
            $this->assertSame($user->id, $saved[0]['user_id']);
            $this->assertSame("Legacy first line\n\nLegacy second paragraph", $saved[1]['body']);
            $this->assertSame('Author not recorded', $saved[1]['author']);
            $this->assertSame($record->status, $record->fresh()->status);
            $originalTime = $saved[0]['datetime'];
            $this->patchJson($url.'/'.$saved[0]['id'], [
                'version' => hash('sha256', $record->fresh()->notes), 'body' => "Edited\nMultiline",
            ])->assertOk();
            $edited = app(RecordNotes::class)->all($record->fresh()->notes);
            $this->assertSame($originalTime, $edited[0]['datetime']);
            $this->assertSame($user->name, $edited[0]['edited_by']);
            $this->assertSame("Edited\nMultiline", $edited[0]['body']);
            $this->deleteJson($url.'/'.$saved[0]['id'], ['version' => hash('sha256', $record->fresh()->notes)])->assertOk();
            $this->assertCount(1, app(RecordNotes::class)->all($record->fresh()->notes));
        }
    }

    public function test_existing_record_access_rules_protect_every_notes_action(): void
    {
        $owner = User::factory()->create(['account_type' => 'Counsellor']);
        $other = User::factory()->create(['account_type' => 'Counsellor']);
        $records = $this->records($owner);
        foreach ($records as $url => $record) {
            $this->postJson($url, ['version' => hash('sha256', ''), 'body' => 'Denied'])->assertUnauthorized();
        }
        $this->actingAs($other);
        foreach ($records as $url => $record) {
            foreach (['postJson', 'patchJson', 'deleteJson'] as $method) {
                $response = $this->$method($url.($method === 'postJson' ? '' : '/not-a-note'), ['version' => hash('sha256', ''), 'body' => 'Denied']);
                $this->assertContains($response->status(), [403, 404]);
            }
            $this->assertNull($record->fresh()->notes);
        }
    }

    public function test_stale_notes_and_invalid_input_cannot_overwrite_saved_notes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        foreach ($this->records($user) as $url => $record) {
            $version = hash('sha256', '');
            $this->postJson($url, ['version' => $version, 'body' => 'Saved'])->assertOk();
            $this->postJson($url, ['version' => $version, 'body' => 'Stale draft'])->assertConflict();
            $currentVersion = hash('sha256', $record->fresh()->notes);
            $this->postJson($url, ['version' => $currentVersion, 'body' => " \n "])->assertUnprocessable();
            $this->postJson($url, ['version' => $currentVersion, 'body' => str_repeat('a', 10001)])->assertUnprocessable();
            $this->patchJson($url.'/not-a-note', ['version' => $currentVersion, 'body' => 'Unknown'])->assertNotFound();
            $this->assertCount(1, app(RecordNotes::class)->all($record->fresh()->notes));
        }
    }

    public function test_legacy_client_notes_sort_by_creation_time_and_keep_author_and_line_breaks(): void
    {
        $user = User::factory()->create();
        $records = $this->records($user);
        $client = reset($records);
        $client->update(['notes' => json_encode([
            ['body' => "Older\nParagraph", 'author' => 'Original author', 'datetime' => '2026-10-01 10:00:00'],
            ['body' => 'Newest note', 'author' => 'Another author', 'datetime' => '2026-10-02 10:00:00'],
            ['body' => 'Undated note'],
        ])]);
        $response = $this->actingAs($user)->get('/clients/'.$client->client_id)->assertOk();
        $response->assertSeeInOrder(['Newest note', 'Older', 'Undated note'])->assertSee('Original author')
            ->assertSee('Add Note')->assertDontSee('Save changes');
        $this->assertSame("Older\nParagraph", app(RecordNotes::class)->all($client->notes)[1]['body']);
    }

    public function test_application_summary_cannot_overwrite_notes_saved_independently(): void
    {
        $user = User::factory()->create();
        $records = $this->records($user);
        $application = end($records);
        $this->actingAs($user)->postJson('/applications/'.$application->id.'/notes', ['version' => hash('sha256', ''), 'body' => 'Keep this note'])->assertOk();
        $stored = $application->fresh()->notes;
        $this->patch('/applications/'.$application->id, [
            'status' => 'In Progress', 'priority' => 'H', 'primary_counsellor_id' => $user->id,
            'notes' => 'Stale full-record form value',
        ])->assertSessionHasNoErrors();
        $this->assertSame($stored, $application->fresh()->notes);
    }
}
