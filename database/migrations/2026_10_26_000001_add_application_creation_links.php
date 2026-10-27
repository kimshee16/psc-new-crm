<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('linked_application_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->foreignId('source_lead_id')->nullable()->constrained('leads')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('linked_application_id');
            $table->dropConstrainedForeignId('source_lead_id');
        });
    }
};
