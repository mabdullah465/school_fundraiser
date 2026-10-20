@php
    $money = fn (int $minor, string $cur = 'USD') => number_format($minor / 100, 2).' '.$cur;
    $maskEmail = function (?string $e) {
        if (! $e || ! str_contains($e, '@')) return '—';
        [$u, $d] = explode('@', $e, 2);
        return mb_substr($u, 0, 1).'***@'.$d;
    };
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta http-equiv="refresh" content="15">
<title>School Fundraiser — Activity</title>
<style>
    :root { --bg:#f7f8fa; --card:#fff; --text:#1f2430; --muted:#6b7280; --line:#e5e7eb; --accent:#2f5bea;
            --ok:#0f7b3f; --okbg:#e3f6ea; --warn:#92580a; --warnbg:#fdf1dc; --bad:#b42318; --badbg:#fde8e6; --info:#1d4ed8; --infobg:#e5edff; }
    @media (prefers-color-scheme: dark) {
        :root { --bg:#12141a; --card:#1a1d26; --text:#e6e8ee; --muted:#9aa1b2; --line:#2a2e3a; --accent:#7b9bff;
                --ok:#6fdc9b; --okbg:#133324; --warn:#f0c26b; --warnbg:#3a2c10; --bad:#ff8f84; --badbg:#3d1a17; --info:#9db8ff; --infobg:#1c2850; }
    }
    * { box-sizing: border-box; }
    body { margin:0; font:14px/1.5 system-ui,-apple-system,Segoe UI,Roboto,sans-serif; background:var(--bg); color:var(--text); }
    main { max-width:1200px; margin:0 auto; padding:20px 16px 60px; }
    header { display:flex; justify-content:space-between; align-items:baseline; flex-wrap:wrap; gap:8px; margin-bottom:16px; }
    h1 { font-size:20px; margin:0; } h2 { font-size:15px; margin:28px 0 8px; }
    .muted { color:var(--muted); font-size:12px; }
    .cards { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:10px; }
    .card { background:var(--card); border:1px solid var(--line); border-radius:10px; padding:12px 14px; }
    .card b { display:block; font-size:22px; } .card span { color:var(--muted); font-size:12px; }
    .wrap { background:var(--card); border:1px solid var(--line); border-radius:10px; overflow-x:auto; }
    table { width:100%; border-collapse:collapse; min-width:640px; }
    th, td { text-align:left; padding:8px 12px; border-bottom:1px solid var(--line); vertical-align:top; white-space:nowrap; }
    th { font-size:12px; color:var(--muted); font-weight:600; } tr:last-child td { border-bottom:0; }
    td.num, th.num { text-align:right; font-variant-numeric:tabular-nums; }
    .pill { display:inline-block; padding:1px 8px; border-radius:99px; font-size:12px; background:var(--infobg); color:var(--info); }
    .ok { background:var(--okbg); color:var(--ok); } .warn { background:var(--warnbg); color:var(--warn); } .bad { background:var(--badbg); color:var(--bad); }
    details summary { cursor:pointer; color:var(--accent); } pre { margin:6px 0 0; padding:10px; background:var(--bg); border:1px solid var(--line);
        border-radius:6px; max-width:560px; max-height:280px; overflow:auto; white-space:pre-wrap; word-break:break-all; font-size:12px; }
    .empty { padding:18px; color:var(--muted); }
    code { font-size:12px; }
</style>
</head>
<body>
<main>
    <header>
        <h1>School Fundraiser — Activity</h1>
        <span class="muted">{{ config('app.env') }} · updated {{ now()->format('M j, Y H:i:s') }} UTC · refreshes every 15 s</span>
    </header>

    <div class="cards">
        <div class="card"><b>{{ $stats['webhooks'] }}</b><span>Webhook deliveries</span></div>
        <div class="card"><b style="color:{{ $stats['webhooks_failed'] ? 'var(--bad)' : 'inherit' }}">{{ $stats['webhooks_failed'] }}</b><span>Failed deliveries</span></div>
        <div class="card"><b>{{ $stats['orders'] }}</b><span>Orders</span></div>
        <div class="card"><b style="color:{{ $stats['quarantined'] ? 'var(--warn)' : 'inherit' }}">{{ $stats['quarantined'] }}</b><span>Quarantined orders</span></div>
        <div class="card"><b>{{ $stats['donations'] }}</b><span>Donations</span></div>
        <div class="card"><b>{{ $money($stats['donated_minor']) }}</b><span>Net donated (ledger)</span></div>
        <div class="card"><b>{{ $stats['schools_approved'] }}</b><span>Approved schools</span></div>
    </div>

    <h2>Webhook deliveries <span class="muted">latest {{ $webhooks->count() }}</span></h2>
    <div class="wrap">
    @if ($webhooks->isEmpty())
        <div class="empty">Nothing received yet. Place a test order or press “Send test notification” in Shopify.</div>
    @else
        <table>
            <tr><th>#</th><th>Received (UTC)</th><th>Topic</th><th>Status</th><th class="num">Tries</th><th>Shop</th><th>Error</th><th>Payload</th></tr>
            @foreach ($webhooks as $w)
                @php $cls = match ($w->status) { 'processed' => 'ok', 'failed' => 'bad', 'ignored' => 'warn', default => '' }; @endphp
                <tr>
                    <td>{{ $w->id }}</td>
                    <td>{{ $w->received_at?->format('M j H:i:s') }}</td>
                    <td><code>{{ $w->topic }}</code></td>
                    <td><span class="pill {{ $cls }}">{{ $w->status }}</span></td>
                    <td class="num">{{ $w->attempts }}</td>
                    <td>{{ $w->shop_domain ?? '—' }}</td>
                    <td style="white-space:normal;max-width:260px;color:var(--bad)">{{ $w->error }}</td>
                    <td>
                        <details><summary>view</summary>
                            <pre>{{ \App\Http\Controllers\DashboardController::redactedPayload($w->payload) }}</pre>
                        </details>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
    </div>

    <h2>Orders <span class="muted">latest {{ $orders->count() }}</span></h2>
    <div class="wrap">
    @if ($orders->isEmpty())
        <div class="empty">No orders yet.</div>
    @else
        <table>
            <tr><th>Order</th><th>Placed</th><th>Customer</th><th>School</th><th class="num">Items</th><th class="num">Total</th><th class="num">Sales tax</th><th>Gateway</th><th class="num">Processing fee</th>
                <th>Shopify status</th><th>Status</th><th>Donation</th></tr>
            @foreach ($orders as $o)
                <tr>
                    <td><b>{{ $o->order_number ?? $o->shopify_order_id }}</b></td>
                    <td>{{ $o->placed_at?->format('M j H:i') ?? '—' }}</td>
                    <td>{{ $o->customer_name ?? '—' }} <span class="muted">{{ $maskEmail($o->customer_email) }}</span></td>
                    <td>
                        @if ($o->school) {{ $o->school->name }}
                        @elseif ($o->selected_school_raw) <span class="muted">unknown: {{ $o->selected_school_raw }}</span>
                        @else <span class="muted">none selected</span> @endif
                    </td>
                    <td class="num">{{ $o->items_count }}</td>
                    <td class="num">{{ $money($o->total_minor, $o->currency) }}</td>
                    <td class="num" title="{{ collect($o->tax_lines ?? [])->map(fn ($t) => $t['title'].($t['rate'] !== null ? ' '.rtrim(rtrim(number_format($t['rate'] * 100, 3), '0'), '.').'%' : '').': '.number_format($t['amount_minor'] / 100, 2))->implode(' | ') }}">{{ $money($o->tax_minor, $o->currency) }}</td>
                    <td>{{ $o->payment_gateway ?? '—' }}</td>
                    <td class="num" title="{{ collect($o->fee_details ?? [])->map(fn ($f) => $f['type'].': '.number_format($f['amount_minor'] / 100, 2))->implode(' | ') }}">
                        @if ($o->fee_status === 'fetched') {{ $money($o->processing_fee_minor, $o->currency) }}
                        @elseif ($o->fee_status === 'pending') <span class="muted">pending…</span>
                        @elseif ($o->fee_status === 'unavailable') <span class="muted">n/a</span>
                        @else <span class="muted">—</span> @endif
                    </td>
                    <td>{{ $o->financial_status ?? '—' }}{{ $o->fulfillment_status ? ' / '.$o->fulfillment_status : '' }}</td>
                    <td><span class="pill">{{ str_replace('_', ' ', $o->display_status->value) }}</span></td>
                    <td>
                        @if ($o->ingestion_status === \App\Enums\IngestionStatus::Quarantined)
                            <span class="pill warn" title="{{ $o->quarantine_reason }}">quarantined: {{ $o->quarantine_reason }}</span>
                        @elseif ($o->allocation)
                            <span class="pill ok">{{ $money($o->allocation->amount_minor, $o->allocation->currency) }}</span>
                        @else
                            <span class="muted">not paid</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </table>
    @endif
    </div>

    <h2>Donations <span class="muted">latest {{ $donations->count() }}</span></h2>
    <div class="wrap">
    @if ($donations->isEmpty())
        <div class="empty">No donations yet. A donation is created when a paid order carries a valid approved school.</div>
    @else
        <table>
            <tr><th>Ref</th><th>Order</th><th>School</th><th class="num">Base</th><th class="num">Rate</th>
                <th class="num">Original</th><th class="num">Current</th><th>Status</th><th>Payable from</th></tr>
            @foreach ($donations as $d)
                <tr>
                    <td><code>{{ $d->public_ref }}</code></td>
                    <td>{{ $d->order->order_number ?? $d->order->shopify_order_id }}</td>
                    <td>{{ $d->school->name }}</td>
                    <td class="num">{{ $money($d->base_minor, $d->currency) }}</td>
                    <td class="num">{{ rtrim(rtrim(number_format($d->rate_bps_snapshot / 100, 2), '0'), '.') }}%</td>
                    <td class="num">{{ $money($d->original_amount_minor, $d->currency) }}</td>
                    <td class="num"><b>{{ $money($d->amount_minor, $d->currency) }}</b></td>
                    <td><span class="pill {{ in_array($d->status->value, ['refunded', 'cancelled']) ? 'bad' : ($d->status->value === 'completed' ? 'ok' : '') }}">{{ $d->status->value }}</span></td>
                    <td>{{ $d->payable_at?->format('M j, Y') }}</td>
                </tr>
            @endforeach
        </table>
    @endif
    </div>

    <h2>Balance per school <span class="muted">sum of ledger entries</span></h2>
    <div class="wrap">
    @if ($balances->isEmpty())
        <div class="empty">No ledger entries yet.</div>
    @else
        <table>
            <tr><th>School</th><th class="num">Balance</th><th class="num">Ledger entries</th></tr>
            @foreach ($balances as $b)
                <tr><td>{{ $b->school->name }}</td><td class="num"><b>{{ $money((int) $b->total) }}</b></td><td class="num">{{ $b->entries }}</td></tr>
            @endforeach
        </table>
    @endif
    </div>
</main>
</body>
</html>
