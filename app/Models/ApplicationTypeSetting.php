<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationTypeSetting extends Model
{
    public const TABS = [
        'tasks' => 'Tasks',
        'rfi' => 'RFI',
        'payments' => 'Payments',
        'quotes' => 'Quotes',
        'sent-emails' => 'Sent Emails',
        'primary-files' => 'Primary Files',
        'forms' => 'Forms',
    ];

    protected $fillable = ['category', 'tabs'];

    protected function casts(): array
    {
        return ['tabs' => 'array'];
    }

    public static function types(): array
    {
        return Application::TYPES + ['SBS' => 'SBS'];
    }

    public static function tabsFor(string $category): array
    {
        if (! array_key_exists($category, self::types())) {
            return [];
        }

        $setting = self::query()->where('category', $category)->first();

        // An absent setting inherits the defaults; an empty list disables all tabs.
        return self::availableTabs($setting?->tabs ?? array_keys(self::TABS));
    }

    public static function availableTabs(array $tabs): array
    {
        return array_filter(self::TABS, fn (string $key) => in_array($key, $tabs, true), ARRAY_FILTER_USE_KEY);
    }
}
