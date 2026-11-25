<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('application_workflows', function (Blueprint $table) {
            $table->json('visa_charges')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('application_workflows', function (Blueprint $table) {
            $table->dropColumn('visa_charges');
        });
    }
};
