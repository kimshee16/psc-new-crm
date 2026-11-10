<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationTypeSetting extends Model
{
    public const TABS = [
        'notes' => 'Notes',
        'tasks' => 'Tasks',
        'lmt' => 'LMT',
        'rfi' => 'RFI',
        'nominations' => 'Nominations',
        'payments' => 'Payments',
        'quotes' => 'Quotes',
        'agreements' => 'Agreements',
        'sent-emails' => 'Sent Emails',
        'primary-files' => 'Primary Files',
        'forms' => 'Forms',
    ];

    public const DEFAULT_TABS = ['tasks', 'rfi', 'payments', 'quotes', 'agreements', 'sent-emails', 'primary-files', 'forms'];

    public const NOMINATION_TABS = ['notes', 'tasks', 'lmt', 'rfi', 'payments', 'agreements'];

    public const SBS_TABS = ['tasks', 'rfi', 'nominations', 'payments', 'agreements'];

    protected $fillable = ['category', 'tabs'];

    protected function casts(): array
    {
        return ['tabs' => 'array'];
    }

    public static function types(): array
    {
        return Application::TYPES + ['SBS' => 'SBS'];
    }

    public static function tabsFor(string $category, ?string $sponsorType = null): array
    {
        if (! array_key_exists($category, self::types())) {
            return [];
        }

        $setting = self::query()->where('category', $category)->first();
        $labour = $category === 'SBS' && strcasecmp(trim((string) $sponsorType), 'Labour Agreement') === 0;

        // An absent setting inherits the defaults; an empty list disables all tabs.
        $defaults = match ($category) {
            'Nomination' => self::NOMINATION_TABS,
            'SBS' => $labour ? self::DEFAULT_TABS : self::SBS_TABS,
            default => self::DEFAULT_TABS,
        };
        $tabs = self::availableTabs($setting?->tabs ?? $defaults);

        return $category === 'SBS' && ! $labour ? $tabs : array_diff_key($tabs, ['nominations' => true]);
    }

    public static function availableTabs(array $tabs): array
    {
        return array_filter(self::TABS, fn (string $key) => in_array($key, $tabs, true), ARRAY_FILTER_USE_KEY);
    }
}
