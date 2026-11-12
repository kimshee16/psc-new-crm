<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the approval dates entered through the former Labour Agreement editor.
        DB::table('applications')->where('category', 'SBS')
            ->whereRaw("LOWER(TRIM(sponsor_type)) = 'labour agreement'")
            ->whereNotNull('approval_date')->whereNull('outcome')
            ->update(['outcome' => 'Approved', 'outcome_date' => DB::raw('approval_date')]);
    }

    public function down(): void
    {
        // Backfilled decisions are retained to avoid deleting subsequent user edits.
    }
};
