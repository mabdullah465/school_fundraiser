<?php

/*
| The "/" page lists webhook deliveries, orders and donations.
|   - no DASHBOARD_PASSWORD  -> open to anyone (default)
|   - DASHBOARD_PASSWORD set -> HTTP Basic auth (user: DASHBOARD_USER, default "admin")
| Emails are masked and contact details are redacted in payloads, but names, schools and
| amounts are visible, so set a password if the URL is public and the data is real.
*/
return [
    'user' => env('DASHBOARD_USER', 'admin'),
    'password' => env('DASHBOARD_PASSWORD'),
    'per_section' => 25,
];
