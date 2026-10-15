<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'priority')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('priority', 20)->default('medium')->after('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('leads', 'priority')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->dropColumn('priority');
            });
        }
    }
};
