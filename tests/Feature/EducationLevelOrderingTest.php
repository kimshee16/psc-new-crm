<?php

namespace Tests\Feature;

use App\Models\EducationLevel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EducationLevelOrderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_move_and_remove_keep_a_unique_contiguous_order(): void
    {
        $this->actingAs(User::factory()->create(['account_type' => 'Admin']));
        EducationLevel::query()->delete();
        foreach (['First', 'Second', 'Third'] as $name) {
            $this->post('/configuration/education-levels', ['name' => $name, 'active' => 1])->assertSessionHasNoErrors();
        }
        $third = EducationLevel::where('name', 'Third')->firstOrFail();
        $this->patch('/configuration/education-levels/'.$third->id, ['name' => 'Third renamed', 'active' => 0, 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->assertSame(['Third renamed', 'First', 'Second'], EducationLevel::orderBy('sort_order')->pluck('name')->all());
        $this->assertFalse($third->fresh()->active);
        $this->post('/configuration/education-levels', ['name' => 'Inserted', 'active' => 1, 'sort_order' => 2])->assertSessionHasNoErrors();
        $this->assertSame(['Third renamed', 'Inserted', 'First', 'Second'], EducationLevel::orderBy('sort_order')->pluck('name')->all());
        $this->patch('/configuration/education-levels/'.$third->id, ['name' => 'Third renamed', 'active' => 1, 'sort_order' => 4])->assertSessionHasNoErrors();
        $this->assertSame(['Inserted', 'First', 'Second', 'Third renamed'], EducationLevel::orderBy('sort_order')->pluck('name')->all());
        $this->delete('/configuration/education-levels/'.$third->id)->assertSessionHasNoErrors();
        $this->assertSame([1, 2, 3], EducationLevel::orderBy('sort_order')->pluck('sort_order')->all());
    }

    public function test_invalid_positions_and_non_admin_changes_are_rejected(): void
    {
        $admin = User::factory()->create(['account_type' => 'Admin']);
        foreach ([0, -1, 1000, 'invalid'] as $position) {
            $this->actingAs($admin)->postJson('/configuration/education-levels', [
                'name' => 'Invalid', 'sort_order' => $position, 'active' => true,
            ])->assertUnprocessable()->assertJsonValidationErrors('sort_order');
        }
        $this->actingAs(User::factory()->create(['account_type' => 'Counsellor']))
            ->post('/configuration/education-levels', ['name' => 'Denied', 'sort_order' => 1])->assertForbidden();
    }
}
