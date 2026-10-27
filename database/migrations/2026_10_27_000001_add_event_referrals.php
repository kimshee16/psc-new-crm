<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_referrals', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->unique();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(1);
            $table->timestamps();
        });

        Schema::table('leads', function (Blueprint $table) {
            $table->string('event_discovery_channel', 160)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropColumn('event_discovery_channel');
        });
        Schema::dropIfExists('event_referrals');
    }
};
