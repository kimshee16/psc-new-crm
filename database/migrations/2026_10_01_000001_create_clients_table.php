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
        Schema::create('clients', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('client_id')->unique();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('first_name', 80);
            $table->string('middle_name', 80)->nullable();
            $table->string('surname', 80)->nullable();
            $table->date('dob')->nullable();
            $table->string('mobile', 40);
            $table->string('email', 120);
            $table->string('nationality', 80);
            $table->string('current_location', 120);
            $table->string('street', 160)->nullable();
            $table->string('suburb', 120)->nullable();
            $table->string('state', 40)->nullable();
            $table->string('postcode', 20)->nullable();
            $table->text('overseas_address')->nullable();
            $table->string('admin_office', 80);
            $table->string('client_status', 40)->default('Active');
            $table->string('current_visa', 120)->nullable();
            $table->date('visa_expiry')->nullable();
            $table->text('notes')->nullable();
            $table->string('primary_counsellor', 120);
            $table->string('secondary_counsellor', 120)->nullable();
            $table->string('migration_agent', 120)->nullable();
            $table->string('tag', 40)->default('Manual');
            $table->timestamps();

            $table->index(['first_name', 'middle_name', 'surname']);
            $table->index(['client_status']);
            $table->index(['created_by_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clients');
    }
};
