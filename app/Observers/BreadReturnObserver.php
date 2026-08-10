<?php

namespace App\Observers;

use App\Models\BreadReturn;
use App\Services\CashMirrorService;

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
    }
}
