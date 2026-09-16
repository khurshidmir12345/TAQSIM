<?php

namespace App\Observers;

use App\Models\BreadReturn;
use App\Models\OutletEntry;
use App\Services\CashMirrorService;
use App\Services\OutletService;

class BreadReturnObserver
{
    public function __construct(
        private readonly CashMirrorService $mirror,
    ) {}

    public function created(BreadReturn $return): void
    {
        $this->mirror->syncReturn($return);
    }

    public function updated(BreadReturn $return): void
    {
        $this->mirror->syncReturn($return);
    }

    public function deleted(BreadReturn $return): void
    {
        $this->mirror->forgetReturn($return);

        // Vozvrat tarixdan o'chirilsa, do'kon daftaridagi "qaytdi" qatori ham
        // ketadi — aks holda do'kon qarzi noto'g'ri kam ko'rinardi. Daftar
        // tomonidan o'chirilayotganda (OutletService) bu yerga kirmaymiz.
        if ($return->outlet_entry_id === null || OutletService::$cascading) {
            return;
        }

        $entry = OutletEntry::query()->find($return->outlet_entry_id);
        if ($entry === null) {
            return;
        }

        $others = BreadReturn::query()
            ->where('outlet_entry_id', $entry->id)
            ->whereKeyNot($return->id)
            ->exists();

        if (! $others) {
            app(OutletService::class)->deleteEntry($entry);
        }
    }
}
