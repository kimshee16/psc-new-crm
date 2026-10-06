<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Support\PSC\LeadAssignmentService;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class WebsiteLeadFormController extends Controller
{
    private const CAPTCHA_SESSION_KEY = 'psc.website_forms.captcha';

    public function __construct(
        private readonly WebsiteLeadReferences $references,
        private readonly LeadAssignmentService $assignmentService,
    ) {
    }

    public function contact(Request $request): View
    {
        $this->ensureCaptcha($request);

        return $this->formView('contact_us', 'Contact Us', route('website.contact.submit'));
    }

    public function consultation(Request $request): View
    {
        $this->ensureCaptcha($request);

        return $this->formView('book_consultation', 'Book a Consultation', route('website.consultation.submit'));
    }

    public function refreshCaptcha(Request $request): JsonResponse
    {
        $this->generateCaptcha($request);

        return response()->json([
            'image_url' => route('website.captcha.image', ['v' => random_int(100000, 999999)]),
        ]);
    }

    public function captchaImage(Request $request)
    {
        $this->ensureCaptcha($request);

        return response($this->captchaSvg((string) $request->session()->get(self::CAPTCHA_SESSION_KEY)), 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }

    public function submitContact(Request $request): RedirectResponse
    {
        return $this->handleSubmission($request, [
            'form_type' => 'contact_us',
            'source' => 'Contact us',
            'redirect' => 'https://progress-study.com/contact-received',
        ]);
    }

    public function submitConsultation(Request $request): RedirectResponse
    {
        return $this->handleSubmission($request, [
            'form_type' => 'book_consultation',
            'source' => 'Book a free consultation',
            'redirect' => 'https://progress-study.com/request-received',
        ]);
    }

    private function formView(string $formType, string $heading, string $action): View
    {
        return view('website.lead-form', [
            'title' => 'Progress Study - '.$heading,
            'heading' => $heading,
            'formType' => $formType,
            'action' => $action,
            'countries' => WebsiteLeadReferences::countries(),
            'phoneCountryCodes' => WebsiteLeadReferences::phoneCountryCodes(),
            'locations' => $this->references->locationNames(),
            'captchaImageUrl' => route('website.captcha.image', ['v' => random_int(100000, 999999)]),
        ]);
    }

    /**
     * @param array{form_type: string, source: string, redirect: string} $form
     */
    private function handleSubmission(Request $request, array $form): RedirectResponse
    {
        $validated = $this->validatedSubmission($request);
        $phoneNumber = $this->normalisePhoneNumber((string) $validated['phone_number']);
        $mobile = $validated['phone_country_code'].' '.$phoneNumber;
        $assignment = $this->assignmentService->assign((string) $validated['current_location']);

        DB::transaction(function () use ($validated, $form, $phoneNumber, $mobile, $assignment): void {
            $client = $this->clientForSubmission($validated, $mobile, $assignment);

            Lead::create([
                'lead_id' => $this->nextLeadId(),
                'client_id' => $client->getKey(),
                'assigned_user_id' => $assignment['user']?->getKey(),
                'assigned_name' => $assignment['name'],
                'assigned_office_code' => $assignment['office_code'],
                'assigned_office' => $assignment['office'],
                'assignment_reason' => $assignment['reason'],
                'form_type' => $form['form_type'],
                'source' => $form['source'],
                'status' => 'New',
                'first_name' => (string) $validated['first_name'],
                'middle_name' => (string) ($validated['middle_name'] ?? ''),
                'surname' => (string) ($validated['surname'] ?? ''),
                'email' => strtolower((string) $validated['email']),
                'phone_country_code' => (string) $validated['phone_country_code'],
                'phone_number' => $phoneNumber,
                'mobile' => $mobile,
                'nationality' => (string) $validated['nationality'],
                'current_location' => (string) $validated['current_location'],
                'enquiry' => (string) ($validated['enquiry'] ?? ''),
                'submitted_at' => now(),
            ]);
        });

        $request->session()->forget(self::CAPTCHA_SESSION_KEY);

        return redirect()->away($form['redirect']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedSubmission(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'first_name' => ['required', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'middle_name' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'surname' => ['nullable', 'string', 'max:80', 'regex:/^[A-Za-z0-9 ]+$/'],
            'email' => ['required', 'email', 'max:120'],
            'phone_country_code' => ['required', 'string', Rule::in(array_keys(WebsiteLeadReferences::phoneCountryCodes()))],
            'phone_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()-]{6,24}$/'],
            'nationality' => ['required', 'string', Rule::in(WebsiteLeadReferences::countries())],
            'current_location' => ['required', 'string', Rule::in($this->references->locationNames())],
            'enquiry' => ['nullable', 'string', 'max:4000'],
            'captcha' => ['required', 'string', 'size:6'],
        ], [], [
            'phone_country_code' => 'country code',
            'phone_number' => 'phone number',
            'current_location' => 'current location',
            'captcha' => 'CAPTCHA code',
        ]);

        $validator->after(function ($validator) use ($request): void {
            $expected = (string) $request->session()->get(self::CAPTCHA_SESSION_KEY, '');
            $actual = strtoupper(trim((string) $request->input('captcha', '')));

            if ($expected === '' || $actual !== $expected) {
                $validator->errors()->add('captcha', 'The CAPTCHA code is incorrect.');
            }
        });

        if ($validator->fails()) {
            $this->generateCaptcha($request);

            throw new ValidationException($validator);
        }

        return $validator->validated();
    }

    /**
     * @param array<string, mixed> $validated
     * @param array{user: \App\Models\User|null, name: string, office_code: string, office: string, reason: string} $assignment
     */
    private function clientForSubmission(array $validated, string $mobile, array $assignment): Client
    {
        $email = strtolower((string) $validated['email']);
        $client = Client::query()
            ->where('email', $email)
            ->orWhere('mobile', $mobile)
            ->first();

        $attributes = [
            'first_name' => (string) $validated['first_name'],
            'middle_name' => (string) ($validated['middle_name'] ?? ''),
            'surname' => (string) ($validated['surname'] ?? ''),
            'mobile' => $mobile,
            'email' => $email,
            'nationality' => (string) $validated['nationality'],
            'current_location' => (string) $validated['current_location'],
            'admin_office' => $assignment['office'],
            'notes' => $this->clientNotes($client?->notes ?? '', (string) ($validated['enquiry'] ?? '')),
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
            'dob' => null,
            'street' => '',
            'suburb' => '',
            'state' => '',
            'postcode' => '',
            'overseas_address' => '',
            'client_status' => 'Prospect',
            'current_visa' => '',
            'visa_expiry' => null,
            'passport_photo_path' => null,
            'primary_counsellor' => $assignment['name'],
            'secondary_counsellor' => '',
            'migration_agent' => '',
            'tag' => 'Web',
        ]);
    }

    private function nextClientId(): int
    {
        return max((int) Client::query()->max('client_id'), 1017) + 1;
    }

    private function nextLeadId(): int
    {
        return max((int) Lead::query()->max('lead_id'), 2200) + 1;
    }

    private function clientNotes(string $existingNotes, string $enquiry): string
    {
        if (trim($enquiry) === '') {
            return $existingNotes;
        }

        $notes = $this->noteRecordsFrom($existingNotes);
        $notes[] = [
            'body' => 'Website enquiry: '.trim($enquiry),
            'author' => 'PSC Website Form',
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

    private function normalisePhoneNumber(string $phoneNumber): string
    {
        return trim((string) preg_replace('/\s+/', ' ', $phoneNumber));
    }

    private function ensureCaptcha(Request $request): void
    {
        if (! $request->session()->has(self::CAPTCHA_SESSION_KEY)) {
            $this->generateCaptcha($request);
        }
    }

    private function generateCaptcha(Request $request): string
    {
        $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $code = '';

        for ($i = 0; $i < 6; $i++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        $request->session()->put(self::CAPTCHA_SESSION_KEY, $code);

        return $code;
    }

    private function captchaSvg(string $code): string
    {
        $backgrounds = ['#d8eef4', '#e9f6f0', '#f3ead7', '#e9edf7'];
        $ink = ['#123348', '#1f7890', '#31556a', '#101820'];
        $svg = [
            '<svg xmlns="http://www.w3.org/2000/svg" width="220" height="76" viewBox="0 0 220 76" role="img" aria-label="CAPTCHA image">',
            '<defs>',
            '<filter id="warp"><feTurbulence type="fractalNoise" baseFrequency="0.025 0.12" numOctaves="2" seed="'.random_int(1, 99).'" result="noise"/><feDisplacementMap in="SourceGraphic" in2="noise" scale="5" xChannelSelector="R" yChannelSelector="G"/></filter>',
            '<linearGradient id="bg" x1="0" x2="1" y1="0" y2="1"><stop offset="0" stop-color="'.$backgrounds[array_rand($backgrounds)].'"/><stop offset="1" stop-color="'.$backgrounds[array_rand($backgrounds)].'"/></linearGradient>',
            '</defs>',
            '<rect width="220" height="76" rx="10" fill="url(#bg)"/>',
        ];

        for ($i = 0; $i < 18; $i++) {
            $svg[] = '<circle cx="'.random_int(4, 216).'" cy="'.random_int(4, 72).'" r="'.random_int(2, 8).'" fill="'.$ink[array_rand($ink)].'" opacity="0.'.random_int(8, 24).'"/>';
        }

        for ($i = 0; $i < 8; $i++) {
            $svg[] = '<path d="M '.random_int(-20, 30).' '.random_int(4, 72).' C '.random_int(40, 90).' '.random_int(-12, 88).', '.random_int(120, 180).' '.random_int(-12, 88).', '.random_int(190, 240).' '.random_int(4, 72).'" stroke="'.$ink[array_rand($ink)].'" stroke-width="'.random_int(1, 3).'" fill="none" opacity="0.'.random_int(18, 40).'"/>';
        }

        $svg[] = '<g filter="url(#warp)" font-family="Verdana, Arial, sans-serif" font-size="30" font-weight="800">';

        foreach (str_split($code) as $index => $character) {
            $svg[] = '<text x="'.(24 + ($index * 30) + random_int(-3, 3)).'" y="'.random_int(43, 54).'" rotate="'.random_int(-18, 18).'" fill="'.$ink[array_rand($ink)].'">'.e($character).'</text>';
        }

        $svg[] = '</g>';

        for ($i = 0; $i < 5; $i++) {
            $svg[] = '<rect x="'.random_int(0, 200).'" y="'.random_int(0, 58).'" width="'.random_int(14, 38).'" height="'.random_int(8, 18).'" rx="4" fill="#ffffff" opacity="0.'.random_int(10, 25).'"/>';
        }

        $svg[] = '</svg>';

        return implode('', $svg);
    }
}
