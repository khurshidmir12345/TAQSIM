<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiLocaleTest extends TestCase
{
    /** @return array<int, string> */
    private function flattenKeys(array $array, string $prefix = ''): array
    {
        $keys = [];

        foreach ($array as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value)) {
                $keys = array_merge($keys, $this->flattenKeys($value, $path));
            } else {
                $keys[] = $path;
            }
        }

        sort($keys);

        return $keys;
    }

    public function test_all_api_locales_have_matching_keys(): void
    {
        $canonical = require lang_path('uz/api.php');
        $canonicalKeys = $this->flattenKeys($canonical);

        foreach (['uz_CYRL', 'ru', 'kk', 'ky', 'tr', 'tg', 'en'] as $locale) {
            $file = lang_path("$locale/api.php");
            $this->assertFileExists($file, "Missing api translation file for [$locale]");

            $keys = $this->flattenKeys(require $file);

            $this->assertSame(
                $canonicalKeys,
                $keys,
                "API translation key mismatch for locale [$locale]",
            );
        }
    }

    public function test_set_api_locale_middleware_supports_english(): void
    {
        $response = $this->withHeader('Accept-Language', 'en')
            ->getJson('/api/ping');

        $response->assertOk()
            ->assertJsonPath('message', __('api.ping', [], 'en'));
    }

    public function test_all_expense_locales_have_matching_keys(): void
    {
        $canonical = require lang_path('uz/expense.php');
        $canonicalKeys = $this->flattenKeys($canonical);

        foreach (['uz_CYRL', 'ru', 'kk', 'ky', 'tr', 'tg', 'en'] as $locale) {
            $file = lang_path("$locale/expense.php");
            $this->assertFileExists($file, "Missing expense translation file for [$locale]");

            $keys = $this->flattenKeys(require $file);

            $this->assertSame(
                $canonicalKeys,
                $keys,
                "Expense translation key mismatch for locale [$locale]",
            );
        }
    }
}
