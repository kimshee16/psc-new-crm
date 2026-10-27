<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('client_id')->constrained()->restrictOnDelete();
            $table->enum('category', ['Study', 'Visa', 'Citizenship', 'Assessment', 'Nomination', 'SBS']);
            $table->string('course')->nullable();
            $table->string('visa_subclass')->nullable();
            $table->string('nomination_pathway')->nullable();
            $table->string('assessment_body')->nullable();
            $table->date('critical_date')->nullable()->index();
            $table->enum('priority', ['H', 'M', 'L'])->default('M');
            $table->string('status', 80)->default('Not Started')->index();
            $table->foreignId('primary_counsellor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('secondary_counsellor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('migration_agent_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
