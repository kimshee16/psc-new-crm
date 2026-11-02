<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_type_settings', function (Blueprint $table) {
            $table->id();
            $table->string('category')->unique();
            $table->json('tabs');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('application_type_settings');
    }
};
