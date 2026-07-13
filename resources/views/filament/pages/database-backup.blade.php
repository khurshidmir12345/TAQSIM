<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::section>
            <x-slot name="heading">Joriy baza</x-slot>
            <x-slot name="description">
                Ushbu sahifadan ma'lumotlar bazasining to'liq zaxira nusxasini
                yuklab olishingiz mumkin. Fayl MySQL/MariaDB ga to'g'ridan-to'g'ri
                import bo'ladi.
            </x-slot>

            <dl class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Baza nomi</dt>
                    <dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $databaseName }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Ulanish</dt>
                    <dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $connectionName }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Server</dt>
                    <dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $databaseHost }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-gray-500 dark:text-gray-400">Port</dt>
                    <dd class="mt-1 font-semibold text-gray-950 dark:text-white">{{ $databasePort }}</dd>
                </div>
            </dl>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Import qilish</x-slot>
            <x-slot name="description">
                Yuklab olingan faylni boshqa MySQL/MariaDB bazasiga quyidagicha
                yuklaysiz:
            </x-slot>

            <div class="space-y-3">
                <div>
                    <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">SQL fayl:</p>
                    <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-sm text-gray-100"><code>mysql -u USER -p DATABASE &lt; backup.sql</code></pre>
                </div>
                <div>
                    <p class="mb-1 text-sm font-medium text-gray-700 dark:text-gray-300">ZIP fayl (avval oching):</p>
                    <pre class="overflow-x-auto rounded-lg bg-gray-950 p-4 text-sm text-gray-100"><code>unzip backup.sql.zip
mysql -u USER -p DATABASE &lt; backup.sql</code></pre>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
