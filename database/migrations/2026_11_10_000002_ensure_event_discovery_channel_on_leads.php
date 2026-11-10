<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('leads', 'event_discovery_channel')) {
            Schema::table('leads', function (Blueprint $table) {
                $table->string('event_discovery_channel', 160)->nullable();
            });
        }
    }

    public function down(): void
    {
        // The original event-referral migration owns this column. Keep recorded answers on rollback.
    }
};
