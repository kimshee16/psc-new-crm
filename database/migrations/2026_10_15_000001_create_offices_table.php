<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name', 80);
            $table->unsignedInteger('office_id')->nullable()->unique();
            $table->boolean('is_default')->default(false);
            $table->string('sender_name', 160)->nullable();
            $table->string('sender_email')->nullable();
            $table->timestamps();
        });

        $offices = [
            ['MDN', 'Medan', 109],
            ['VNO', 'Vietnam', 108],
            ['LATAM', 'LATAM', 107],
            ['BRA', 'Brazil', 106],
            ['PHIL-ONSHORE', 'Philippines Onshore', 105],
            ['MNL', 'Manila', 104],
            ['SYD', 'Sydney', 103],
            ['MEL', 'Melbourne', 102],
            ['JKT', 'Jakarta', 101],
            ['DPS', 'Bali', 100],
            // Preserve office codes already supported by the CRM, without inventing PSC IDs.
            ['BNE', 'Brisbane', null],
            ['PER', 'Perth', null],
            ['ADL', 'Adelaide', null],
            ['MANILA', 'Manila', null],
            ['PHIL-OFFSHORE', 'Philippines Offshore', null],
        ];

        foreach ($offices as [$code, $name, $officeId]) {
            DB::table('offices')->insert([
                'code' => $code,
                'name' => $name,
                'office_id' => $officeId,
                'is_default' => $code === 'MEL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('offices');
    }
};
