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
        Schema::create('leads', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('lead_id')->unique();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('assigned_name', 120);
            $table->string('assigned_office_code', 40);
            $table->string('assigned_office', 80);
            $table->string('assignment_reason', 255);
            $table->string('form_type', 40);
            $table->string('source', 80);
            $table->string('status', 40)->default('New');
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('surname', 80)->nullable();
            $table->string('email', 120);
            $table->string('phone_country_code', 8);
            $table->string('phone_number', 40);
            $table->string('mobile', 60);
            $table->string('nationality', 80);
            $table->string('current_location', 120);
            $table->text('enquiry')->nullable();
            $table->timestamp('submitted_at');
            $table->timestamps();

            $table->index(['assigned_user_id', 'status']);
            $table->index(['assigned_office_code', 'status']);
            $table->index(['form_type']);
            $table->index(['email']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
