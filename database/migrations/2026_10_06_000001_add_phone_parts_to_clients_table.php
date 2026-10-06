<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->string('phone_country_code', 8)->nullable()->after('dob');
            $table->string('phone_number', 40)->nullable()->after('phone_country_code');
        });

        DB::table('clients')
            ->select(['id', 'mobile'])
            ->orderBy('id')
            ->each(function (object $client): void {
                $mobile = trim((string) $client->mobile);
                $countryCode = '+61';
                $phoneNumber = $mobile;

                if (preg_match('/^(\+\d{1,3})\s*(.+)$/', $mobile, $matches) === 1) {
                    $countryCode = $matches[1];
                    $phoneNumber = trim($matches[2]);
                }

                DB::table('clients')
                    ->where('id', $client->id)
                    ->update([
                        'phone_country_code' => $countryCode,
                        'phone_number' => $phoneNumber,
                    ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('clients', function (Blueprint $table) {
            $table->dropColumn(['phone_country_code', 'phone_number']);
        });
    }
};
