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
        $this->syncEducationLevels([
            'Junior High / Secondary (below Year 12)',
            'Senior High School (Year 12 / equivalent)',
            'Certificate I',
            'Certificate II',
            'Certificate III',
            'Certificate IV',
            'Diploma',
            'Advanced Diploma / Associate Degree',
            'Bachelor Degree',
            'Graduate Certificate',
            'Graduate Diploma',
            'Master Degree',
            'Doctoral Degree (PhD)',
            'Other / Not specified',
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->syncEducationLevels([
            'High school',
            'Certificate',
            'Diploma',
            'Advanced diploma',
            'Bachelor degree',
            'Postgraduate certificate',
            'Postgraduate diploma',
            'Masters degree',
            'Doctorate',
            'Other / Not specified',
        ]);
    }

    /**
     * @param list<string> $educationLevels
     */
    private function syncEducationLevels(array $educationLevels): void
    {
        $now = now();

        DB::table('education_levels')
            ->whereNotIn('name', $educationLevels)
            ->update([
                'active' => false,
                'updated_at' => $now,
            ]);

        foreach ($educationLevels as $index => $educationLevel) {
            DB::table('education_levels')->insertOrIgnore([
                'name' => $educationLevel,
                'active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('education_levels')
                ->where('name', $educationLevel)
                ->update([
                    'active' => true,
                    'sort_order' => $index + 1,
                    'updated_at' => $now,
                ]);
        }
    }
};
