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
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('psc_event_id')->nullable()->after('source')->constrained('psc_events')->nullOnDelete();
            $table->string('event_name', 160)->nullable()->after('psc_event_id');

            $table->index(['psc_event_id']);
            $table->index(['event_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['psc_event_id']);
            $table->dropIndex(['psc_event_id']);
            $table->dropIndex(['event_name']);
            $table->dropColumn(['psc_event_id', 'event_name']);
        });
    }
};
