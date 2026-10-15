<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->syncLocations([
            ['name' => 'Indonesia - Jawa Timur', 'office_code' => 'JKT'],
            ['name' => 'Indonesia - Jawa Barat & Banten', 'office_code' => 'JKT'],
            ['name' => 'Indonesia - Medan', 'office_code' => 'MDN'],
            ['name' => 'Australia - Other locations', 'office_code' => 'SYD'],
            ['name' => 'Indonesia - Other locations', 'office_code' => 'DPS'],
            ['name' => 'Indonesia - Kalimantan', 'office_code' => 'JKT'],
            ['name' => 'Indonesia - Sumatera', 'office_code' => 'JKT'],
            ['name' => 'Indonesia - Jawa Tengah', 'office_code' => 'JKT'],
            ['name' => 'Middle East', 'office_code' => 'MNL'],
            ['name' => 'Other Location', 'office_code' => 'MEL'],
            ['name' => 'LATAM', 'office_code' => 'LATAM'],
            ['name' => 'Philippines', 'office_code' => 'MNL'],
            ['name' => 'Indonesia - Bali', 'office_code' => 'DPS'],
            ['name' => 'Indonesia - Jakarta', 'office_code' => 'JKT'],
            ['name' => 'Australia - Melbourne', 'office_code' => 'MEL'],
            ['name' => 'Australia - Sydney', 'office_code' => 'SYD'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('locations')
            ->whereIn('name', [
                'Indonesia - Jawa Timur',
                'Indonesia - Jawa Barat & Banten',
                'Indonesia - Medan',
                'Australia - Other locations',
                'Indonesia - Other locations',
                'Indonesia - Kalimantan',
                'Indonesia - Sumatera',
                'Indonesia - Jawa Tengah',
                'Middle East',
                'Other Location',
                'LATAM',
                'Philippines',
                'Indonesia - Bali',
                'Indonesia - Jakarta',
                'Australia - Melbourne',
                'Australia - Sydney',
            ])
            ->delete();
    }

    /**
     * @param list<array{name: string, office_code: string}> $locations
     */
    private function syncLocations(array $locations): void
    {
        $now = now();

        foreach ($locations as $index => $location) {
            DB::table('locations')->insertOrIgnore([
                'name' => $location['name'],
                'office_code' => $location['office_code'],
                'active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('locations')
                ->where('name', $location['name'])
                ->update([
                    'office_code' => $location['office_code'],
                    'active' => true,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                ]);
        }
    }
};
