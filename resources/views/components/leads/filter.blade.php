@props(['field', 'label', 'options', 'selected' => [], 'placeholder'])

<button id="leads-{{ $field }}" type="button" class="leads-filter-trigger" popovertarget="leads-{{ $field }}-menu" aria-label="Filter by {{ $label }}" aria-haspopup="dialog" aria-expanded="false">
    <span data-filter-summary>{{ count($selected) ? count($selected).' selected' : $placeholder }}</span>
    <span aria-hidden="true">▾</span>
</button>
<div id="leads-{{ $field }}-menu" popover="auto" role="dialog" aria-label="Filter by {{ $label }}" class="leads-filter-menu" data-filter="{{ $field }}" data-placeholder="{{ $placeholder }}">
    <input type="search" class="leads-filter-search" placeholder="Search {{ strtolower($label) }}…" aria-label="Search {{ strtolower($label) }}" autocomplete="off">
    <div class="leads-filter-actions">
        <button type="button" data-filter-all>Select all</button>
        <button type="button" data-filter-clear>Clear</button>
    </div>
    <div class="leads-filter-options">
        @foreach ($options as $value => $text)
            <label class="leads-filter-option">
                <input type="checkbox" value="{{ $value }}" @checked(in_array((string) $value, $selected, true))>
                <span>{{ $text }}</span>
            </label>
        @endforeach
        <p data-filter-empty hidden>No matching options.</p>
    </div>
    <div class="leads-filter-footer">
        <span data-filter-count aria-live="polite"></span>
        <button type="button" data-filter-cancel>Cancel</button>
        <button type="button" data-filter-done>Done</button>
    </div>
</div>
