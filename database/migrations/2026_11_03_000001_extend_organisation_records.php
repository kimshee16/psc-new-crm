<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organisations', function (Blueprint $table) {
            // The existing name column is the organisation's legal name.
            $table->string('trading_name')->nullable();
            $table->string('abn', 11)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::create('organisation_contacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('role')->nullable();
            $table->timestamps();
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->string('sponsor_type')->nullable();
            $table->date('approval_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->string('home_affairs_reference')->nullable();
            $table->string('trn')->nullable();
            $table->string('nominated_position_title')->nullable();
            $table->string('outcome')->nullable();
            $table->string('nominee_name')->nullable();
            $table->foreignId('nominee_client_id')->nullable()->constrained('clients')->nullOnDelete();
        });

        Schema::create('application_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 20);
            $table->string('title');
            $table->string('status', 80)->nullable();
            $table->string('reference')->nullable();
            $table->date('due_date')->nullable();
            $table->decimal('amount', 12, 2)->nullable();
            $table->string('currency', 3)->nullable();
            $table->timestamps();
            $table->index(['application_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_activities');
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('nominee_client_id');
            $table->dropColumn(['sponsor_type', 'approval_date', 'expiry_date', 'home_affairs_reference', 'trn', 'nominated_position_title', 'outcome', 'nominee_name']);
        });
        Schema::dropIfExists('organisation_contacts');
        Schema::table('organisations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropColumn(['trading_name', 'abn', 'address']);
        });
    }
};
