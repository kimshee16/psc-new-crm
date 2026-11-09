<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('sponsorship_id')->nullable()->constrained('applications')->nullOnDelete();
            $table->string('anzsco_code', 6)->nullable();
            $table->decimal('annual_salary', 12, 2)->nullable();
            $table->date('lodgement_date')->nullable();
            $table->string('reference_number')->nullable();
            $table->date('outcome_date')->nullable();
            $table->date('appeal_deadline')->nullable();
            $table->string('appeal_status')->nullable();
            $table->string('art_case_number')->nullable();
            $table->string('final_outcome')->nullable();
            $table->date('final_outcome_date')->nullable();
            $table->string('google_drive_url', 2048)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sponsorship_id');
            $table->dropColumn(['anzsco_code', 'annual_salary', 'lodgement_date', 'reference_number',
                'outcome_date', 'appeal_deadline', 'appeal_status', 'art_case_number',
                'final_outcome', 'final_outcome_date', 'google_drive_url']);
        });
    }
};
