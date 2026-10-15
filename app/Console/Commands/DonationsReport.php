<?php

namespace App\Console\Commands;

use App\Enums\IngestionStatus;
use App\Models\DonationAllocation;
use App\Models\DonationLedgerEntry;
use App\Models\ShopifyOrder;
use Illuminate\Console\Command;

class DonationsReport extends Command
{
    protected $signature = 'donations:report';

    protected $description = 'Show what the backend has recorded from Shopify (allocations per school, quarantined orders)';

    public function handle(): int
    {
        $rows = DonationAllocation::with(['school', 'order'])->latest('id')->limit(25)->get()->map(fn ($a) => [
            $a->public_ref,
            $a->order->order_number,
            $a->school->name,
            $this->money($a->base_minor, $a->currency),
            ($a->rate_bps_snapshot / 100).'%',
            $this->money($a->amount_minor, $a->currency),
            $a->status->value,
        ]);

        $this->info('Latest allocations');
        $this->table(['Ref', 'Order', 'School', 'Base', 'Rate', 'Donation', 'Status'], $rows);

        $this->info('Ledger balance per school (sum of signed entries)');
        $this->table(['School', 'Balance'], DonationLedgerEntry::query()
            ->selectRaw('school_id, sum(amount_minor) as total')
            ->groupBy('school_id')
            ->with('school')
            ->get()
            ->map(fn ($e) => [$e->school->name, $this->money((int) $e->total, 'USD')]));

        $quarantined = ShopifyOrder::where('ingestion_status', IngestionStatus::Quarantined->value)->get();
        $this->info('Quarantined orders (paid, no valid school, nobody credited)');
        $this->table(['Order', 'Reason', 'School attribute received'], $quarantined->map(fn ($o) => [
            $o->order_number, $o->quarantine_reason, $o->selected_school_raw ?? '(none)',
        ]));

        return self::SUCCESS;
    }

    private function money(int $minor, string $currency): string
    {
        return number_format($minor / 100, 2).' '.$currency;
    }
}
