<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120)->unique();
            $table->foreignId('regional_manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('office_region', function (Blueprint $table) {
            $table->foreignId('office_id')->constrained()->cascadeOnDelete();
            $table->foreignId('region_id')->constrained()->cascadeOnDelete();
            $table->primary(['office_id', 'region_id']);
        });

        foreach ([
            ['AUS', ['MEL', 'SYD'], null],
            ['LATAM', ['BRA', 'LATAM'], 'Jennie Cay'],
            ['Philippines', ['MNL', 'PHIL-ONSHORE'], 'Am Milan'],
        ] as [$name, $codes, $managerName]) {
            // Link only an unambiguous, existing regional manager account.
            $managers = $managerName === null ? collect() : DB::table('users')
                ->where('name', $managerName)->where('account_type', 'Regional manager')->pluck('id');
            $regionId = DB::table('regions')->insertGetId([
                'name' => $name,
                'regional_manager_id' => $managers->count() === 1 ? $managers->first() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            foreach (DB::table('offices')->whereIn('code', $codes)->pluck('id') as $officeId) {
                DB::table('office_region')->insert(['office_id' => $officeId, 'region_id' => $regionId]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('office_region');
        Schema::dropIfExists('regions');
    }
};
