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
        Schema::create('lead_redirections', function (Blueprint $table) {
            $table->id();
            $table->string('nationality', 80);
            $table->string('current_location', 120);
            $table->string('office_code', 40);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['nationality', 'current_location']);
            $table->index(['active', 'nationality', 'current_location']);
            $table->index(['office_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lead_redirections');
    }
};
