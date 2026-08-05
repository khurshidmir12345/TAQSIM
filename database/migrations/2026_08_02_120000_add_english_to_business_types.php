<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @return array<string, array{name_en: string, terminology: array<string, string>}> */
    private function english(): array
    {
        return require database_path('data/business_type_english.php');
    }

    public function up(): void
    {
        Schema::table('business_types', function (Blueprint $table): void {
            $table->string('name_en')->nullable()->after('name_tr');
        });

        foreach ($this->english() as $key => $english) {
            $row = DB::table('business_types')->where('key', $key)->first();

            if (! $row) {
                continue;
            }

            $terminology = json_decode((string) $row->terminology, true) ?? [];

            if (! isset($terminology['en'])) {
                $terminology['en'] = $english['terminology'];
            }

            DB::table('business_types')->where('key', $key)->update([
                'name_en' => $row->name_en ?: $english['name_en'],
                'terminology' => json_encode($terminology, JSON_UNESCAPED_UNICODE),
            ]);
        }
    }

    public function down(): void
    {
        foreach (array_keys($this->english()) as $key) {
            $row = DB::table('business_types')->where('key', $key)->first();

            if (! $row) {
                continue;
            }

            $terminology = json_decode((string) $row->terminology, true) ?? [];
            unset($terminology['en']);

            DB::table('business_types')->where('key', $key)->update([
                'name_en' => null,
                'terminology' => json_encode($terminology, JSON_UNESCAPED_UNICODE),
            ]);
        }

        Schema::table('business_types', function (Blueprint $table): void {
            $table->dropColumn('name_en');
        });
    }
};
