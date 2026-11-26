<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_catalog_entries', function (Blueprint $table) {
            $table->id();
            $table->string('catalog', 32);
            $table->string('type', 40)->default('');
            $table->string('name');
            $table->unique(['catalog', 'type', 'name']);
        });

        DB::table('application_catalog_entries')->insert([
            ['catalog' => 'actions', 'name' => 'Send Service Agreement'],
            ['catalog' => 'actions', 'name' => 'Send Program Options'],
            ['catalog' => 'actions', 'name' => 'Request OSHC'],
            ['catalog' => 'actions', 'name' => 'Book an appointment'],
            ['catalog' => 'modules', 'name' => 'Program Options'],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('application_catalog_entries');
    }
};
