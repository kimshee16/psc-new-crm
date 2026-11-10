<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            foreach (['initial_consultation', 'service_agreement', 'supporting_documents', 'application_stage'] as $field) {
                $table->string($field, 80)->default('To do');
            }
            $table->index(['category', 'expiry_date'], 'applications_sbs_expiry_index');
        });
        // Existing sponsorships recorded approval before outcome fields were available.
        DB::table('applications')->where('category', 'SBS')->whereNotNull('approval_date')->whereNull('outcome')
            ->whereRaw("LOWER(TRIM(COALESCE(sponsor_type, ''))) != 'labour agreement'")
            ->update(['outcome' => 'Approved', 'outcome_date' => DB::raw('approval_date')]);
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropIndex('applications_sbs_expiry_index');
            $table->dropColumn(['initial_consultation', 'service_agreement', 'supporting_documents', 'application_stage']);
        });
    }
};
