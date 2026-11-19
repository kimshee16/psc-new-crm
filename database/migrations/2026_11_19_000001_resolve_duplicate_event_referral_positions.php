<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            $options = DB::table('event_referrals')->orderBy('sort_order')->orderBy('name')->orderBy('id')->get(['id', 'sort_order']);

            if ($options->count() === $options->unique('sort_order')->count()) {
                return;
            }

            // Keep the existing order in registration forms when resolving ties.
            foreach ($options as $index => $option) {
                DB::table('event_referrals')->where('id', $option->id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function down(): void
    {
        // Keep the current positions, which administrators may have edited since the migration.
    }
};
