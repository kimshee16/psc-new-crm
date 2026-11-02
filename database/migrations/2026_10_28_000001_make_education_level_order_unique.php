<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Preserve the existing display order while resolving duplicate positions.
        foreach (DB::table('education_levels')->orderBy('sort_order')->orderBy('name')->orderBy('id')->pluck('id') as $index => $id) {
            DB::table('education_levels')->where('id', $id)->update(['sort_order' => $index + 1]);
        }
        Schema::table('education_levels', fn (Blueprint $table) => $table->unique('sort_order'));
    }

    public function down(): void
    {
        Schema::table('education_levels', fn (Blueprint $table) => $table->dropUnique(['sort_order']));
    }
};
