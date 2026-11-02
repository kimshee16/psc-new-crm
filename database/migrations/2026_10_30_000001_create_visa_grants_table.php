<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('current_visa', 255)->nullable()->change();
        });
        Schema::create('visa_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->foreignId('client_id')->constrained()->cascadeOnDelete();
            $table->string('visa_subclass');
            $table->date('effective_from');
            $table->date('expires_on');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['client_id', 'effective_from']);
            $table->index(['processed_at', 'cancelled_at', 'effective_from'], 'visa_grants_due_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visa_grants');
        // Keep the wider visa field to avoid truncating existing subclass labels.
    }
};
