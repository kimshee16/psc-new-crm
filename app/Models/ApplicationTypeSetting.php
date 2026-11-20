<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationTypeSetting extends Model
{
    public const LIST_FIELDS = [
        'email_templates' => 'Email templates',
        'required_documents' => 'Required documents',
        'forms' => 'Forms',
        'actions' => 'Actions',
    ];

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

    protected $fillable = ['category', 'tabs', 'defaults', 'checkpoint_ids'];

    protected function casts(): array
    {
        return ['tabs' => 'array', 'checkpoint_ids' => 'array', 'defaults' => 'array'];
    }

    public static function defaultAgent(string $category, ?ApplicationWorkflow $workflow = null): ?int
    {
        if ($workflow?->migration_agent_id !== null || $workflow?->defaults !== null) {
            return $workflow->migration_agent_id;
        }

        return self::query()->where('category', $category)->first()?->defaults['migration_agent_id'] ?? null;
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

        // An absent setting inherits the defaults; an empty list disables all tabs.
        $tabs = self::availableTabs($setting?->tabs ?? self::defaultTabs($category));

        return $category === 'SBS' ? $tabs : array_diff_key($tabs, ['nominations' => true]);
    }

    public static function defaultTabs(string $category): array
    {
        return match ($category) {
            'Nomination' => self::NOMINATION_TABS,
            'SBS' => self::SBS_TABS,
            default => self::DEFAULT_TABS,
        };
    }

    public static function availableTabs(array $tabs): array
    {
        return array_filter(self::TABS, fn (string $key) => in_array($key, $tabs, true), ARRAY_FILTER_USE_KEY);
    }
}
