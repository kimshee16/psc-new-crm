<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organisations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('application_workflows', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('category');
            $table->json('summary_options')->nullable();
            $table->foreignId('migration_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('application_workflow_user', function (Blueprint $table) {
            $table->foreignId('application_workflow_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->primary(['application_workflow_id', 'user_id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->unsignedBigInteger('client_id')->nullable()->change();
            $table->enum('category', ['Study', 'Visa', 'Citizenship', 'Assessment', 'Nomination', 'SBS', 'ART Appeal'])->change();
            $table->foreignId('organisation_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('application_workflow_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('psc_client')->nullable();
            $table->longText('notes')->nullable();
        });
    }

    public function down(): void
    {
        // Do not discard organisation ownership or appeal records during rollback.
        if (DB::table('applications')->whereNull('client_id')->orWhere('category', 'ART Appeal')->exists()) {
            throw new RuntimeException('Migrate organisation applications and ART appeals before rolling back the summary schema.');
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organisation_id');
            $table->dropConstrainedForeignId('application_workflow_id');
            $table->dropColumn(['psc_client', 'notes']);
            $table->unsignedBigInteger('client_id')->nullable(false)->change();
            $table->enum('category', ['Study', 'Visa', 'Citizenship', 'Assessment', 'Nomination', 'SBS'])->change();
        });
        Schema::dropIfExists('application_workflow_user');
        Schema::dropIfExists('application_workflows');
        Schema::dropIfExists('organisations');
    }
};
