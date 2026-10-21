<?php

return [

    /*
    | Public site indexing. While false, every response carries
    | "X-Robots-Tag: noindex, nofollow" and pages render a noindex meta tag.
    | Sprint 2c moves this behind the `public_site_indexing` feature flag.
    */
    /*
    | Times are stored in UTC; this is the timezone shown to people.
    */
    'display_timezone' => env('DISPLAY_TIMEZONE', 'Africa/Johannesburg'),

    'indexing' => (bool) env('PUBLIC_SITE_INDEXING', false),

    /*
    | Content Security Policy. Report-only lets you observe violations
    | before enforcing (useful when adding third-party scripts).
    */
    'csp' => [
        'enabled' => (bool) env('CSP_ENABLED', true),
        'report_only' => (bool) env('CSP_REPORT_ONLY', false),
    ],

];
