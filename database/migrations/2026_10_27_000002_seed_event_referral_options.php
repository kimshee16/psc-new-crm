<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach ([
            'WhatsApp',
            'Instagram',
            'Facebook',
            'Website',
            'Word of Mouth',
            'Celebgram Campaign',
        ] as $index => $name) {
            DB::table('event_referrals')->insertOrIgnore([
                'name' => $name,
                'active' => true,
                'sort_order' => $index + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Retain configuration data that administrators may have edited or used.
    }
};
