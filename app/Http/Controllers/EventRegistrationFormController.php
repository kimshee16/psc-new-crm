<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EventReferral;
use App\Models\Lead;
use App\Models\PscEvent;
use App\Models\User;
use App\Support\PSC\LeadAssignmentService;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EventRegistrationFormController extends Controller
{
    public function __construct(
        private readonly WebsiteLeadReferences $references,
        private readonly LeadAssignmentService $assignmentService,
    ) {}

    public function show(PscEvent $event): View
    {
        abort_unless($event->active, 404);

        return view('website.event-registration-form', $this->viewData($event) + [
            'action' => route('event-registration.submit', $event),
        ]);
    }

    public function submit(Request $request, PscEvent $event): RedirectResponse
    {
        abort_unless($event->active, 404);

        $validated = $this->validatedSubmission($request);
        $phoneNumber = $this->normalisePhoneNumber((string) $validated['phone_number']);
        $mobile = $validated['phone_country_code'].' '.$phoneNumber;
        $educationLevel = $this->educationLevelFrom($validated);
        $assignment = $this->assignmentService->assign(
            (string) $validated['nationality'],
            (string) $validated['current_location']
        );

        DB::transaction(function () use ($validated, $event, $phoneNumber, $mobile, $educationLevel, $assignment): void {
            $client = $this->clientForSubmission($validated, $phoneNumber, $mobile, $assignment);

            Lead::create([
                'lead_id' => $this->nextLeadId(),
                'client_id' => $client->getKey(),
                'assigned_user_id' => $assignment['user']?->getKey(),
                'assigned_name' => $assignment['name'],
                'assigned_office_code' => $assignment['office_code'],
                'assigned_office' => $assignment['office'],
                'assignment_reason' => $assignment['reason'],
                'form_type' => 'event_registration',
                'source' => 'Event',
                'psc_event_id' => $event->getKey(),
                'event_name' => $event->name,
                'event_discovery_channel' => EventReferral::findOrFail($validated['event_referral_id'])->name,
                'status' => 'New',
                'first_name' => (string) $validated['first_name'],
                'middle_name' => (string) ($validated['middle_name'] ?? ''),
                'surname' => (string) ($validated['surname'] ?? ''),
                'dob' => $validated['dob'] ?? null,
                'email' => strtolower((string) $validated['email']),
                'phone_country_code' => (string) $validated['phone_country_code'],
                'phone_number' => $phoneNumber,
                'mobile' => $mobile,
                'nationality' => (string) $validated['nationality'],
                'current_location' => (string) $validated['current_location'],
                'highest_education_level' => $educationLevel,
                'enquiry' => '',
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route('event-registration.thanks', $event);
    }

    public function thanks(PscEvent $event): View
    {
        abort_unless($event->active, 404);

        return view('website.event-registration-thanks', $this->viewData($event));
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(PscEvent $event): array
    {
        return [
            'title' => 'Registration - '.$event->name,
            'event' => $event,
            'eventReferrals' => EventReferral::activeOptions(),
            'countries' => WebsiteLeadReferences::countries(),
            'phoneCountryCodes' => WebsiteLeadReferences::phoneCountryCodes(),
            'locations' => $this->references->locationNames(),
            'educationLevels' => $this->references->educationLevelNames(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSubmission(Request $request): array
    {
        $educationLevels = $this->references->educationLevelNames();

        $validator = Validator::make($request->all(), [
            'event_referral_id' => ['required', 'integer', Rule::exists('event_referrals', 'id')->where('active', true)],
            'first_name' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'middle_name' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'surname' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'email' => ['required', 'email', 'max:120'],
            'phone_country_code' => ['required', 'string', Rule::in(array_keys(WebsiteLeadReferences::phoneCountryCodes()))],
            'phone_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()-]{6,24}$/'],
            'dob' => ['nullable', 'date', 'before_or_equal:today'],
            'nationality' => ['required', 'string', Rule::in(WebsiteLeadReferences::countries())],
            'current_location' => ['required', 'string', Rule::in($this->references->locationNames())],
            'highest_education_level' => ['nullable', 'string', Rule::in($educationLevels)],
            'highest_education_level_other' => ['nullable', 'string', 'max:160'],
        ], [
            'event_referral_id.required' => 'Please select how you heard about our event.',
            'event_referral_id.exists' => 'Please select an available option for how you heard about our event.',
        ], [
            'event_referral_id' => 'how you heard about our event',
            'phone_country_code' => 'country code',
            'phone_number' => 'phone number',
            'dob' => 'date of birth',
            'current_location' => 'current location',
            'highest_education_level' => 'highest education level',
            'highest_education_level_other' => 'other highest education level',
        ]);

        $validator->sometimes(
            'highest_education_level_other',
            ['required', 'string', 'max:160'],
            fn ($input): bool => (string) ($input->highest_education_level ?? '') === 'Other / Not specified'
        );

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function educationLevelFrom(array $validated): string
    {
        $selected = trim((string) ($validated['highest_education_level'] ?? ''));

        if ($selected !== 'Other / Not specified') {
            return $selected;
        }

        return trim((string) ($validated['highest_education_level_other'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $validated
     * @param  array{user: User|null, name: string, office_code: string, office: string, reason: string}  $assignment
     */
    private function clientForSubmission(array $validated, string $phoneNumber, string $mobile, array $assignment): Client
    {
        $email = strtolower((string) $validated['email']);
        $phoneCountryCode = (string) $validated['phone_country_code'];
        $client = $this->clientMatchingSubmission($validated);

        $attributes = [
            'first_name' => (string) $validated['first_name'],
            'middle_name' => (string) ($validated['middle_name'] ?? $client?->middle_name ?? ''),
            'surname' => (string) ($validated['surname'] ?? $client?->surname ?? ''),
            'dob' => $validated['dob'] ?? $client?->dob,
            'phone_country_code' => $phoneCountryCode,
            'phone_number' => $phoneNumber,
            'mobile' => $mobile,
            'email' => $email,
            'nationality' => (string) $validated['nationality'],
            'current_location' => (string) $validated['current_location'],
            'admin_office' => $assignment['office'],
        ];

        if ($client !== null) {
            $client->fill($attributes + [
                'primary_counsellor' => $client->primary_counsellor ?: $assignment['name'],
            ]);
            $client->save();

            return $client;
        }

        return Client::create($attributes + [
            'client_id' => $this->nextClientId(),
            'created_by_user_id' => $assignment['user']?->getKey(),
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'passport_photo_path' => null,
            'notes' => '',
            'primary_counsellor' => $assignment['name'],
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Event',
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function clientMatchingSubmission(array $validated): ?Client
    {
        $surname = trim((string) ($validated['surname'] ?? ''));
        $dob = $validated['dob'] ?? null;
        $query = Client::query()
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower(trim((string) $validated['first_name']))]);

        if ($surname !== '') {
            $query->whereRaw("LOWER(TRIM(COALESCE(surname, ''))) = ?", [strtolower($surname)]);
        }
        if ($dob !== null) {
            $query->whereDate('dob', $dob);
        }

        if ($surname !== '' && $dob !== null) {
            return $query->orderBy('id')->first();
        }

        // With incomplete identity details, require a unique name/email match.
        $matches = $query
            ->whereRaw('LOWER(TRIM(email)) = ?', [strtolower(trim((string) $validated['email']))])
            ->limit(2)->get();

        return $matches->count() === 1 ? $matches->first() : null;
    }

    private function nextClientId(): int
    {
        return max((int) Client::query()->max('client_id'), 1017) + 1;
    }

    private function nextLeadId(): int
    {
        return max((int) Lead::query()->max('lead_id'), 2200) + 1;
    }

    private function normalisePhoneNumber(string $phoneNumber): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $phoneNumber));
    }
}
