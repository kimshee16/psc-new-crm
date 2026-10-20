<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class UserConfigurationController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        return $this->save($request, new User);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        return $this->save($request, $user);
    }

    public function updateMany(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $rules = ['users' => ['required', 'array', 'min:1'], 'users.*.id' => ['required', 'integer', 'distinct', 'exists:users,id']];
        foreach ($this->accessRules() as $field => $fieldRules) {
            $rules['users.*.'.$field] = $fieldRules;
        }
        $validated = $request->validate($rules);

        $savedUsers = DB::transaction(function () use ($request, $validated) {
            $users = User::query()->whereIn('id', array_column($validated['users'], 'id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($validated['users'] as $index => $data) {
                $user = $users->get($data['id']);
                abort_unless($user, 404);
                $this->validateAccess($request, $user, $data, 'users.'.$index.'.');
                $user->fill(Arr::only($data, array_keys($this->accessRules())))->save();
            }

            return $users;
        });

        return $this->saved($savedUsers);
    }

    public function picture(Request $request, User $user): StreamedResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($user->picture_path && Storage::disk('local')->exists($user->picture_path), 404);

        return Storage::disk('local')->response($user->picture_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }

    private function save(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAdmin($request);
        $request->merge(['email' => is_string($request->input('email')) ? strtolower($request->input('email')) : $request->input('email')]);
        $validated = $request->validate($this->accessRules() + [
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone_country_code' => ['required', 'string', 'regex:/^\+[1-9][0-9]{0,3}$/'],
            'phone_number' => ['required', 'string', 'max:40', 'regex:/^[0-9 ()\-]{4,40}$/'],
            'picture' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ]);
        $this->validateAccess($request, $user, $validated);
        unset($validated['picture']);
        $validated['name'] = $validated['first_name'].' '.$validated['last_name'];
        $validated['email'] = strtolower($validated['email']);
        if (! $user->exists) {
            $validated['password'] = Str::random(64);
        }
        $oldPicture = $user->picture_path;
        $newPicture = $request->hasFile('picture') ? $request->file('picture')->store('user-pictures', 'local') : null;
        abort_if($newPicture === false, 500, 'The picture could not be saved.');
        if ($newPicture) {
            $validated['picture_path'] = $newPicture;
        }
        try {
            DB::transaction(function () use ($user, $validated) {
                if ($user->exists && $user->email !== $validated['email']) {
                    Password::deleteToken($user);
                    $user->email_verified_at = null;
                }
                $user->fill($validated)->save();
            });
        } catch (\Throwable $exception) {
            if ($newPicture) {
                Storage::disk('local')->delete($newPicture);
            }
            throw $exception;
        }
        if ($newPicture && $oldPicture) {
            Storage::disk('local')->delete($oldPicture);
        }

        return $this->saved([$user]);
    }

    private function accessRules(): array
    {
        return [
            'account_type' => ['required', Rule::in(User::CONFIGURABLE_ROLES)],
            'is_active' => ['required', 'boolean'],
            'include_in_round_robin' => ['required', 'boolean'],
            'offices' => ['required', 'array', 'min:1'],
            'offices.*' => ['required', 'string', 'exists:offices,code'],
            'default_office_code' => ['required', 'string', 'exists:offices,code'],
            'portal_invite_status' => ['required', Rule::in(User::INVITE_STATUSES)],
        ];
    }

    private function validateAccess(Request $request, User $user, array $data, string $prefix = ''): void
    {
        if (count($data['offices']) !== count(array_unique($data['offices']))) {
            throw ValidationException::withMessages([$prefix.'offices' => 'Select each office only once.']);
        }
        if ($data['portal_invite_status'] === 'Send Invite' && ! $data['is_active']) {
            throw ValidationException::withMessages([$prefix.'portal_invite_status' => 'Activate the user before sending an invitation.']);
        }
        if (! in_array($data['default_office_code'], $data['offices'], true)) {
            throw ValidationException::withMessages([$prefix.'default_office_code' => 'Default Office must be one of the selected offices.']);
        }
        if ($user->is($request->user()) && (! in_array($data['account_type'], [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true)
            || ! $data['is_active'] || $data['portal_invite_status'] === 'Restrict Access')) {
            throw ValidationException::withMessages([$prefix.'account_type' => 'You cannot remove your own administrator access.']);
        }
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->account_type, [User::ACCOUNT_TYPE_ADMIN, 'Super Admin'], true), 403);
    }

    private function saved(iterable $users): RedirectResponse
    {
        $failed = false;
        foreach ($users as $user) {
            if ($user->portal_invite_status !== 'Send Invite') {
                continue;
            }
            try {
                $status = Password::sendResetLink(['email' => $user->email], function (User $recipient, string $token) {
                    $recipient->notify(new UserInvitation($token));
                });
                if ($status === Password::RESET_LINK_SENT) {
                    $user->update(['portal_invite_status' => 'Invite Sent']);
                } else {
                    $failed = true;
                }
            } catch (\Throwable $exception) {
                report($exception);
                $failed = true;
            }
        }
        $response = redirect()->route('configuration', ['tab' => 'users'])->with('success', 'User changes were saved.');

        return $failed ? $response->with('error', 'An invitation could not be sent. Check mail delivery settings, wait one minute and retry Send Invite.') : $response;
    }
}
