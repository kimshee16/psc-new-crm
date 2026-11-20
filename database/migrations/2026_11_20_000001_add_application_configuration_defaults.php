<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_type_settings', function (Blueprint $table) {
            $table->json('defaults')->nullable();
        });

        Schema::table('application_workflows', function (Blueprint $table) {
            $table->json('defaults')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('application_workflows', fn (Blueprint $table) => $table->dropColumn('defaults'));
        Schema::table('application_type_settings', fn (Blueprint $table) => $table->dropColumn('defaults'));
    }
};
