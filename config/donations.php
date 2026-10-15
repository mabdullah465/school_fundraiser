<?php

return [

    /*
    | Days an allocation stays "pending" before it becomes payable.
    | Gives time for refunds before money moves to the school.
    */
    'hold_days' => (int) env('DONATION_HOLD_DAYS', 14),

    /*
    | What the percentage is applied to. Shown to the storefront.
    */
    'calculation_basis' => 'discounted_eligible_merchandise_subtotal_excluding_tax_shipping',

    /*
    | Shopify product ids that do not count towards the donation base.
    */
    'excluded_product_ids' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('DONATION_EXCLUDED_PRODUCT_IDS', ''))
    ))),

    /*
    | How long the public donation-settings response is cached (seconds).
    */
    'settings_cache_seconds' => 60,
];
