<?php

namespace App\Services;

use App\Enums\OutletEntryType;
use App\Models\BreadCategory;
use App\Models\Outlet;
use App\Models\OutletEntry;
use App\Models\Shop;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Do'konlar daftari: berilgan mahsulot, qaytgan mahsulot, to'lovlar va balans.
 *
 * Balans = Σ berildi − Σ qaytdi − Σ to'landi. Musbat — do'kon qarzdor,
 * manfiy — do'kon oldindan to'lab qo'ygan (keyingi berishda hisobga olinadi).
 */
class OutletService
{
    public function __construct(
        private readonly CashMirrorService $mirror,
    ) {}

    /**
     * Do'konlar bo'yicha jami summalar — bitta so'rovda.
     *
     * @return array<string, array{delivered: float, returned: float, paid: float, balance: float}>
     */
    public function totalsByOutlet(Shop $shop): array
    {
        // DB::table — model cast (enum) ishlamasin, `type` xom satr bo'lib kelsin.
        $rows = DB::table('outlet_entries')
            ->where('shop_id', $shop->id)
            ->selectRaw('outlet_id, type, SUM(amount) as total')
            ->groupBy('outlet_id', 'type')
            ->get();

        $out = [];
        foreach ($rows as $row) {
            $t = &$out[$row->outlet_id];
            $t ??= ['delivered' => 0.0, 'returned' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
            match ($row->type) {
                OutletEntryType::Delivery->value => $t['delivered'] += (float) $row->total,
                OutletEntryType::Return->value => $t['returned'] += (float) $row->total,
                OutletEntryType::Payment->value => $t['paid'] += (float) $row->total,
                default => null,
            };
            $t['balance'] = round($t['delivered'] - $t['returned'] - $t['paid'], 2);
            unset($t);
        }

        return $out;
    }

    /** @return array{delivered: float, returned: float, paid: float, balance: float} */
    public function totalsFor(Outlet $outlet): array
    {
        return $this->totalsByOutlet($outlet->shop)[$outlet->id]
            ?? ['delivered' => 0.0, 'returned' => 0.0, 'paid' => 0.0, 'balance' => 0.0];
    }

    /**
     * Yangi qator. `delivery` bilan birga `paid_amount` kelsa — o'sha kunga
     * bog'langan `payment` qatori ham yoziladi (naqd berildi).
     *
     * @param  array<string,mixed>  $data
     */
    public function createEntry(Shop $shop, Outlet $outlet, array $data, ?string $userId): OutletEntry
    {
        $type = OutletEntryType::from($data['type']);

        return DB::transaction(function () use ($shop, $outlet, $data, $type, $userId) {
            $items = [];
            $amount = 0.0;

            if ($type->hasItems()) {
                $prices = BreadCategory::query()
                    ->where('shop_id', $shop->id)
                    ->whereIn('id', collect($data['items'])->pluck('bread_category_id'))
                    ->pluck('selling_price', 'id');

                foreach ($data['items'] as $item) {
                    $qty = (float) $item['quantity'];
                    $price = array_key_exists('unit_price', $item) && $item['unit_price'] !== null
                        ? (float) $item['unit_price']
                        : (float) ($prices[$item['bread_category_id']] ?? 0);
                    $subtotal = round($qty * $price, 2);
                    $amount += $subtotal;
                    $items[] = [
                        'bread_category_id' => $item['bread_category_id'],
                        'quantity' => $qty,
                        'unit_price' => $price,
                        'subtotal' => $subtotal,
                    ];
                }
            } else {
                $amount = (float) $data['amount'];
            }

            $entry = OutletEntry::create([
                'shop_id' => $shop->id,
                'outlet_id' => $outlet->id,
                'type' => $type,
                'date' => $data['date'],
                'amount' => round($amount, 2),
                'note' => $data['note'] ?? null,
                'created_by' => $userId,
            ]);

            foreach ($items as $item) {
                $entry->items()->create($item);
            }

            if ($type === OutletEntryType::Payment) {
                $this->mirror->syncOutletPayment($entry);
            }

            $paidNow = (float) ($data['paid_amount'] ?? 0);
            if ($type === OutletEntryType::Delivery && $paidNow > 0) {
                $payment = OutletEntry::create([
                    'shop_id' => $shop->id,
                    'outlet_id' => $outlet->id,
                    'type' => OutletEntryType::Payment,
                    'date' => $data['date'],
                    'amount' => round($paidNow, 2),
                    'related_entry_id' => $entry->id,
                    'created_by' => $userId,
                ]);
                $this->mirror->syncOutletPayment($payment);
            }

            if ($type === OutletEntryType::Delivery) {
                $this->mirror->syncOutletDelivery($entry);
            }

            return $entry;
        });
    }

    /** Qatorni (va unga bog'langan naqd to'lovni) o'chiradi; kassa aksi ham ketadi. */
    public function deleteEntry(OutletEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $linked = OutletEntry::query()->where('related_entry_id', $entry->id)->get();
            foreach ($linked as $row) {
                $this->mirror->forgetOutletPayment($row);
                $row->delete();
            }
            $this->mirror->forgetOutletPayment($entry);
            $this->mirror->forgetOutletDelivery($entry);
            $entry->delete();
        });
    }

    /**
     * Do'kon daftari: sana bo'yicha kamayib, mahsulotlari bilan.
     *
     * @return Collection<int, OutletEntry>
     */
    public function entries(Outlet $outlet, ?string $from, ?string $to): Collection
    {
        $query = $outlet->entries()
            ->with(['items.breadCategory'])
            ->orderByDesc('date')
            ->orderByDesc('created_at');

        if ($from !== null) {
            $query->whereDate('date', '>=', $from);
        }
        if ($to !== null) {
            $query->whereDate('date', '<=', $to);
        }

        return $query->get();
    }

    /**
     * Sana oralig'ida do'konlar bo'yicha jami.
     *
     * Kunlik foyda uchun: nasiya = berildi − qaytdi − to'landi (daftardagi
     * qoldiq bilan bir xil mantiq). Qaytgan mahsulot sotilmagan — u alohida
     * `returned` sifatida tushumdan ayriladi.
     *
     * @return array{delivered: float, returned: float, paid: float, credit: float}
     */
    public function periodTotals(Shop $shop, string $from, string $to): array
    {
        $rows = DB::table('outlet_entries')
            ->where('shop_id', $shop->id)
            ->whereDate('date', '>=', $from)
            ->whereDate('date', '<=', $to)
            ->selectRaw('type, SUM(amount) as total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $delivered = (float) ($rows[OutletEntryType::Delivery->value] ?? 0);
        $returned = (float) ($rows[OutletEntryType::Return->value] ?? 0);
        $paid = (float) ($rows[OutletEntryType::Payment->value] ?? 0);

        return [
            'delivered' => round($delivered, 2),
            'returned' => round($returned, 2),
            'paid' => round($paid, 2),
            'credit' => round($delivered - $returned - $paid, 2),
        ];
    }
}
