<?php

namespace App\Observers;

use App\Models\Production;
use App\Services\CashMirrorService;
use App\Services\InventoryService;

/**
 * Mahsulot chiqimi kassadagi aksini o'zi bilan olib yuradi.
 *
 * Observer ishlatilgani bejiz emas: chiqim API'dan ham, admin paneldan ham
 * o'zgaradi — kassani sinxronlashni bitta joyda ushlab turish kerak.
 */
class ProductionObserver
{
    public function __construct(
        private readonly CashMirrorService $mirror,
        private readonly InventoryService $inventory,
    ) {}

    public function created(Production $production): void
    {
        $this->mirror->syncProduction($production);
        $this->inventory->syncProduction($production);
    }

    public function updated(Production $production): void
    {
        $this->mirror->syncProduction($production);
        $this->inventory->syncProduction($production);
    }

    public function deleted(Production $production): void
    {
        $this->mirror->forgetProduction($production);
    }

    /**
     * Ombor harakatlari o'chirishdan OLDIN — aks holda FK kaskadi ularni
     * o'chirib yuboradi va qoldiq qayta hisoblanmay qoladi.
     */
    public function deleting(Production $production): void
    {
        $this->inventory->forgetProduction($production);
    }
}
