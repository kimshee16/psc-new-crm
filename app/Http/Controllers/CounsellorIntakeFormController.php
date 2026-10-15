<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Models\User;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CounsellorIntakeFormController extends Controller
{
    public function __construct(private readonly WebsiteLeadReferences $references)
    {
    }

    public function show(User $counsellor): View
    {
        return view('website.counsellor-intake-form', $this->viewData($counsellor) + [
            'action' => route('counsellor-intake.submit', $counsellor),
        ]);
    }

    public function submit(Request $request, User $counsellor): RedirectResponse
    {
        $validated = $this->validatedSubmission($request);
        $phoneNumber = $this->normalisePhoneNumber((string) $validated['phone_number']);
        $mobile = $validated['phone_country_code'].' '.$phoneNumber;
        $assignment = $this->assignmentFor($counsellor);

        DB::transaction(function () use ($validated, $phoneNumber, $mobile, $assignment): void {
            $client = $this->clientForSubmission($validated, $phoneNumber, $mobile, $assignment);

            Lead::create([
                'lead_id' => $this->nextLeadId(),
                'client_id' => $client->getKey(),
                'assigned_user_id' => $assignment['user']->getKey(),
                'assigned_name' => $assignment['name'],
                'assigned_office_code' => $assignment['office_code'],
                'assigned_office' => $assignment['office'],
                'assignment_reason' => 'PSC-7 intake form submitted from '.$assignment['name'].' direct QR/link.',
                'form_type' => 'counsellor_intake',
                'source' => 'PSC-7 counsellor intake',
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
                'highest_education_level' => (string) ($validated['highest_education_level'] ?? ''),
                'enquiry' => (string) ($validated['enquiry'] ?? ''),
                'submitted_at' => now(),
            ]);
        });

        return redirect()->route('counsellor-intake.thanks', $assignment['user']);
    }

    public function thanks(User $counsellor): View
    {
        return view('website.counsellor-intake-thanks', $this->viewData($counsellor));
    }

    /**
     * @return array<string, mixed>
     */
    private function viewData(User $counsellor): array
    {
        return [
            'title' => 'Progress Study Consultancy - Intake',
            'counsellor' => $counsellor,
            'counsellorFirstName' => $this->firstName($counsellor),
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
            'first_name' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'middle_name' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'surname' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'email' => ['required', 'email', 'max:120'],
            'phone_country_code' => ['required', 'string', Rule::in(array_keys(WebsiteLeadReferences::phoneCountryCodes()))],
            'phone_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()-]{6,24}$/'],
            'dob' => ['required', 'date', 'before_or_equal:today'],
            'nationality' => ['required', 'string', Rule::in(WebsiteLeadReferences::countries())],
            'current_location' => ['required', 'string', Rule::in($this->references->locationNames())],
            'highest_education_level' => ['nullable', 'string', Rule::in($educationLevels)],
            'enquiry' => ['nullable', 'string', 'max:4000'],
        ], [], [
            'phone_country_code' => 'country code',
            'phone_number' => 'phone number',
            'dob' => 'date of birth',
            'current_location' => 'current location',
            'highest_education_level' => 'highest education level',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @return array{user: User, name: string, office_code: string, office: string}
     */
    private function assignmentFor(User $counsellor): array
    {
        $officeCode = $counsellor->assignedOfficeCodes()[0] ?? 'MEL';
        $offices = WebsiteLeadReferences::offices();

        return [
            'user' => $counsellor,
            'name' => $counsellor->name,
            'office_code' => $officeCode,
            'office' => $offices[$officeCode] ?? $officeCode,
        ];
    }

    /**
     * @param array<string, mixed> $validated
     * @param array{user: User, name: string, office_code: string, office: string} $assignment
     */
    private function clientForSubmission(array $validated, string $phoneNumber, string $mobile, array $assignment): Client
    {
        $email = strtolower((string) $validated['email']);
        $phoneCountryCode = (string) $validated['phone_country_code'];
        $client = $this->clientMatchingSubmission($validated);

        $attributes = [
            'first_name' => (string) $validated['first_name'],
            'middle_name' => (string) ($validated['middle_name'] ?? ''),
            'surname' => (string) ($validated['surname'] ?? ''),
            'dob' => $validated['dob'],
            'phone_country_code' => $phoneCountryCode,
            'phone_number' => $phoneNumber,
            'mobile' => $mobile,
            'email' => $email,
            'nationality' => (string) $validated['nationality'],
            'current_location' => (string) $validated['current_location'],
            'admin_office' => $assignment['office'],
            'notes' => $this->clientNotes(
                $client?->notes ?? '',
                (string) ($validated['enquiry'] ?? ''),
                (string) ($validated['highest_education_level'] ?? ''),
                $assignment['name']
            ),
            'primary_counsellor' => $assignment['name'],
        ];

        if ($client !== null) {
            $client->fill($attributes);
            $client->save();

            return $client;
        }

        return Client::create($attributes + [
            'client_id' => $this->nextClientId(),
            'created_by_user_id' => $assignment['user']->getKey(),
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'passport_photo_path' => null,
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Walk-in',
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function clientMatchingSubmission(array $validated): ?Client
    {
        return Client::query()
            ->whereRaw('LOWER(TRIM(first_name)) = ?', [strtolower(trim((string) $validated['first_name']))])
            ->whereRaw("LOWER(TRIM(COALESCE(surname, ''))) = ?", [strtolower(trim((string) $validated['surname']))])
            ->whereDate('dob', (string) $validated['dob'])
            ->orderBy('id')
            ->first();
    }

    private function nextClientId(): int
    {
        return max((int) Client::query()->max('client_id'), 1017) + 1;
    }

    private function nextLeadId(): int
    {
        return max((int) Lead::query()->max('lead_id'), 2200) + 1;
    }

    private function clientNotes(string $existingNotes, string $enquiry, string $education, string $author): string
    {
        $notes = $this->noteRecordsFrom($existingNotes);
        $body = trim(implode("\n", array_filter([
            trim($education) !== '' ? 'Highest education level: '.trim($education) : '',
            trim($enquiry) !== '' ? 'Enquiry: '.trim($enquiry) : '',
        ])));

        if ($body === '') {
            return $existingNotes;
        }

        $notes[] = [
            'body' => $body,
            'author' => $author.' intake form',
            'datetime' => now()->format('Y-m-d H:i:s'),
        ];

        return json_encode($notes, JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{body: string, author: string, datetime: string}>
     */
    private function noteRecordsFrom(string $notes): array
    {
        if (trim($notes) === '') {
            return [];
        }

        $decoded = json_decode($notes, true);

        if (is_array($decoded)) {
            return array_values(array_filter(array_map(function ($note): ?array {
                if (! is_array($note)) {
                    return null;
                }

                $body = trim((string) ($note['body'] ?? ''));

                if ($body === '') {
                    return null;
                }

                return [
                    'body' => $body,
                    'author' => trim((string) ($note['author'] ?? 'Unknown user')) ?: 'Unknown user',
                    'datetime' => trim((string) ($note['datetime'] ?? 'Date not recorded')) ?: 'Date not recorded',
                ];
            }, $decoded)));
        }

        return array_map(fn (string $note): array => [
            'body' => $note,
            'author' => 'Existing record',
            'datetime' => 'Date not recorded',
        ], preg_split("/\R{2,}/", trim($notes)) ?: []);
    }

    private function firstName(User $counsellor): string
    {
        $parts = preg_split('/\s+/', trim($counsellor->name)) ?: [];

        return $parts[0] ?? 'Counsellor';
    }

    private function normalisePhoneNumber(string $phoneNumber): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $phoneNumber));
    }
}
