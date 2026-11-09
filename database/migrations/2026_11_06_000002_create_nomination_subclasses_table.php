<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nomination_subclasses', function (Blueprint $table) {
            $table->id();
            $table->string('subclass', 3)->unique();
            $table->boolean('sbs_required')->default(false);
            $table->timestamps();
        });

        foreach (['482' => true, '186' => false, '494' => true, '491' => false] as $subclass => $required) {
            DB::table('nomination_subclasses')->insert([
                'subclass' => (string) $subclass, 'sbs_required' => $required,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('nomination_subclasses');
    }
};
