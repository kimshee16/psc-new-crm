<?php

namespace Tests\Feature;

use App\Models\PscEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicFormValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_public_forms_show_every_invalid_field_together_and_keep_entered_values(): void
    {
        $counsellor = User::factory()->create();
        $event = PscEvent::create(['name' => 'Study Fair', 'starts_at' => now()->addDay(), 'location' => 'Sydney', 'active' => true]);
        $common = ['first_name', 'email', 'phone_country_code', 'phone_number', 'nationality', 'current_location'];
        $forms = [
            [route('counsellor-intake.show', $counsellor), [...$common, 'dob']],
            [$event->registrationUrl(), [...$common, 'event_referral_id']],
            [url('/contact'), [...$common, 'surname', 'dob', 'captcha']],
            [url('/book-a-free-consultation'), [...$common, 'surname', 'dob', 'captcha']],
        ];

        foreach ($forms as [$url, $fields]) {
            $initial = $this->get($url)->assertOk();
            $this->assertFormBypassesNativeValidation($initial->getContent());
            $this->from($url)->post($url, ['email' => 'invalid-email', 'middle_name' => 'Retained'])
                ->assertRedirect($url)->assertSessionHasErrors($fields);
            $html = $this->get($url)->assertOk()->getContent();
            $document = new \DOMDocument;
            @$document->loadHTML($html);
            $xpath = new \DOMXPath($document);

            foreach ($fields as $field) {
                $error = $xpath->query('//span[@id="'.$field.'-error"]');
                $control = $xpath->query('//*[@name="'.$field.'" and @aria-invalid="true" and @aria-describedby="'.$field.'-error"]');
                $this->assertSame(1, $error->length, $url.' must display the '.$field.' error');
                $this->assertNotSame('', trim($error->item(0)->textContent));
                $this->assertSame(1, $control->length, $field.' must be linked to its error');
            }
            $this->assertSame('Retained', $xpath->query('//input[@name="middle_name"]')->item(0)->getAttribute('value'));
            $this->assertSame('invalid-email', $xpath->query('//input[@name="email"]')->item(0)->getAttribute('value'));
            $this->assertDatabaseCount('clients', 0);
            $this->assertDatabaseCount('leads', 0);
        }
    }

    private function assertFormBypassesNativeValidation(string $html): void
    {
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $this->assertSame(1, (new \DOMXPath($document))->query('//form[@method="POST" and @novalidate]')->length);
    }
}
