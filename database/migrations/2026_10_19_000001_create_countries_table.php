<?php

use App\Support\PSC\WebsiteLeadReferences;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique();
            $table->string('legacy_nationality', 80)->nullable()->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        $legacyNationalities = [
            'Australia' => 'Australian', 'Brazil' => 'Brazilian', 'China' => 'Chinese',
            'India' => 'Indian', 'Indonesia' => 'Indonesian', 'Nepal' => 'Nepalese',
            'Philippines' => 'Philippine',
        ];
        $now = now();
        foreach (WebsiteLeadReferences::defaultCountries() as $name) {
            DB::table('countries')->insert([
                'name' => $name, 'legacy_nationality' => $legacyNationalities[$name] ?? null,
                'active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('countries');
    }
};
