<?php

namespace App\Http\Controllers;

use App\Enums\IngestionStatus;
use App\Models\DonationAllocation;
use App\Models\DonationLedgerEntry;
use App\Models\School;
use App\Models\ShopifyOrder;
use App\Models\WebhookInbox;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    private const REDACT_KEYS = ['email', 'phone', 'address1', 'address2', 'zip', 'latitude', 'longitude'];

    /**
     * Pretty-printed payload with contact details replaced. The full body stays in webhook_inbox;
     * this page only needs to show structure and amounts.
     */
    public static function redactedPayload(string $json): string
    {
        $data = json_decode($json, true);

        if (! is_array($data)) {
            return '(unreadable payload)';
        }

        array_walk_recursive($data, function (&$value, $key) {
            if (in_array($key, self::REDACT_KEYS, true) && $value !== null && $value !== '') {
                $value = '[redacted]';
            }
        });

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function __invoke(): View
    {
        $limit = config('dashboard.per_section');

        return view('dashboard', [
            'stats' => [
                'webhooks' => WebhookInbox::count(),
                'webhooks_failed' => WebhookInbox::where('status', 'failed')->count(),
                'orders' => ShopifyOrder::count(),
                'quarantined' => ShopifyOrder::where('ingestion_status', IngestionStatus::Quarantined->value)->count(),
                'donations' => DonationAllocation::count(),
                'donated_minor' => (int) DonationLedgerEntry::sum('amount_minor'),
                'schools_approved' => School::approved()->count(),
            ],
            'webhooks' => WebhookInbox::latest('id')->limit($limit)->get(),
            'orders' => ShopifyOrder::with(['school', 'allocation'])->withCount('items')->latest('id')->limit($limit)->get(),
            'donations' => DonationAllocation::with(['school', 'order'])->latest('id')->limit($limit)->get(),
            'balances' => DonationLedgerEntry::query()
                ->selectRaw('school_id, sum(amount_minor) as total, count(*) as entries')
                ->groupBy('school_id')
                ->with('school')
                ->orderByDesc('total')
                ->get(),
        ]);
    }
}
