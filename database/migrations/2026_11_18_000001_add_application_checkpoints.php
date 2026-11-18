<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('checkpoints', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->json('statuses');
            $table->softDeletes();
            $table->timestamps();
        });

        foreach (['application_type_settings', 'application_workflows'] as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->json('checkpoint_ids')->nullable();
            });
        }

        Schema::table('applications', function (Blueprint $table) {
            $table->json('checkpoint_statuses')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('applications', fn (Blueprint $table) => $table->dropColumn('checkpoint_statuses'));
        foreach (['application_type_settings', 'application_workflows'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('checkpoint_ids'));
        }
        Schema::dropIfExists('checkpoints');
    }
};
