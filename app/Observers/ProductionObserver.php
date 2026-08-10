<?php

namespace App\Observers;

use App\Models\Production;
use App\Services\CashMirrorService;

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
    ) {}

    public function created(Production $production): void
    {
        $this->mirror->syncProduction($production);
    }

    public function updated(Production $production): void
    {
        $this->mirror->syncProduction($production);
    }

    public function deleted(Production $production): void
    {
        $this->mirror->forgetProduction($production);
    }
}
