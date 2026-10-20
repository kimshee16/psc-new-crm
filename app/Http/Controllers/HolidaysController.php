<?php

namespace App\Http\Controllers;

use App\Models\StaffLeave;
use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class HolidaysController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $canViewStaff = $user->canAccessMyOffices() || $user->account_type === 'Super Admin';
        $officeCodes = $canViewStaff ? $user->accessibleOfficeCodes() : [];
        $showStaff = $canViewStaff && $request->query('view') === 'staff';

        $leaves = StaffLeave::query()->with('user');
        if ($showStaff && $officeCodes !== []) {
            $leaves->where(function (Builder $query) use ($user, $officeCodes) {
                $query->where('user_id', $user->id)
                    ->orWhereHas('user', function (Builder $staff) use ($officeCodes) {
                        $staff->where(function (Builder $offices) use ($officeCodes) {
                            foreach ($officeCodes as $code) {
                                $offices->orWhereJsonContains('offices', $code);
                            }
                        });
                    });
            });
        } else {
            $leaves->where('user_id', $user->id);
        }

        return view('crm.holidays.index', [
            'title' => 'PSC CRM - Holidays',
            'leaves' => $leaves->orderByDesc('starts_on')->orderByDesc('id')->paginate(20)->withQueryString(),
            'canViewStaff' => $canViewStaff,
            'showStaff' => $showStaff,
            'offices' => WebsiteLeadReferences::offices(),
            'officeCodes' => $officeCodes,
        ]);
    }

    public function create(): View
    {
        return $this->form(new StaffLeave);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->user()->staffLeaves()->create($this->validatedDates($request));

        return redirect()->route('holidays')->with('success', 'Leave period recorded.');
    }

    public function edit(Request $request, StaffLeave $holiday): View
    {
        $this->authorizeOwner($request, $holiday);
        abort_unless($holiday->active, 404);

        return $this->form($holiday);
    }

    public function update(Request $request, StaffLeave $holiday): RedirectResponse
    {
        $this->authorizeOwner($request, $holiday);
        abort_unless($holiday->active, 404);
        $holiday->update($this->validatedDates($request));

        return redirect()->route('holidays')->with('success', 'Leave period updated.');
    }

    public function cancel(Request $request, StaffLeave $holiday): RedirectResponse
    {
        $this->authorizeOwner($request, $holiday);
        $holiday->active = false;
        $holiday->save();

        return redirect()->route('holidays')->with('success', 'Leave period cancelled.');
    }

    private function form(StaffLeave $holiday): View
    {
        return view('crm.holidays.form', [
            'title' => 'PSC CRM - '.($holiday->exists ? 'Edit Leave' : 'Record Leave'),
            'holiday' => $holiday,
        ]);
    }

    private function authorizeOwner(Request $request, StaffLeave $holiday): void
    {
        abort_unless((int) $holiday->user_id === (int) $request->user()->id, 403);
    }

    private function validatedDates(Request $request): array
    {
        return $request->validate([
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:starts_on'],
            'reason' => ['nullable', 'string', 'max:160'],
        ], [], ['starts_on' => 'start date', 'ends_on' => 'end date']);
    }
}
