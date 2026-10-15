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
        Schema::create('psc_events', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->dateTime('starts_at');
            $table->string('location', 180);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->index(['active', 'starts_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('psc_events');
    }
};
