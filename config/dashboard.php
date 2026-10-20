<?php

/*
| The "/" page lists webhook deliveries, orders and donations. It shows customer data and raw
| payloads, so it is protected:
|   - DASHBOARD_PASSWORD set   -> HTTP Basic auth (user: DASHBOARD_USER, default "admin")
|   - not set, APP_ENV=local   -> open (your machine only)
|   - not set, anywhere else   -> 404, as if the page did not exist
*/
return [
    'user' => env('DASHBOARD_USER', 'admin'),
    'password' => env('DASHBOARD_PASSWORD'),
    'per_section' => 25,
];
