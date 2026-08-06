<x-filament-panels::page>
    <x-filament::section>
        <x-slot name="heading">Yangi bildirishnoma</x-slot>
        <x-slot name="description">
            Xabar foydalanuvchining telefoniga push bo'lib boradi va ilova ichidagi
            bildirishnomalar ro'yxatida saqlanadi. Foydalanuvchi push'ni o'chirib
            qo'ygan bo'lsa ham, xabarni ilovada ko'radi.
        </x-slot>

        {{ $this->form }}
    </x-filament::section>
</x-filament-panels::page>
