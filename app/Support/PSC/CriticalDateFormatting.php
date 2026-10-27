<?php

namespace App\Support\PSC;

use Illuminate\Support\Carbon;

class CriticalDateFormatting
{
    /** Category thresholds are configured once PSC-4's exact rules are supplied. */
    public function forDate(?string $date, string $category): array
    {
        if (! $date) {
            return ['tone' => 'none', 'label' => 'No critical date'];
        }

        $days = (int) Carbon::today()->diffInDays(Carbon::parse($date)->startOfDay(), false);
        if ($days < 0) {
            return ['tone' => 'red', 'label' => 'Lapsed'];
        }

        $thresholds = config('applications.critical_date_thresholds.'.$category, []);
        foreach (['red', 'amber'] as $tone) {
            if (isset($thresholds[$tone]) && $days <= $thresholds[$tone]) {
                return ['tone' => $tone, 'label' => $days === 0 ? 'Due today' : 'Due in '.$days.' days'];
            }
        }

        return ['tone' => 'none', 'label' => $days === 0 ? 'Due today' : 'Due in '.$days.' days'];
    }
}
