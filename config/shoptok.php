<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Base Configuration for Shoptok Crawler
    |--------------------------------------------------------------------------
    |
    | Centralized configuration for all Shoptok crawling endpoints and defaults.
    | Keeps URLs, headers, and browser behavior in one consistent place.
    |
    */

    'base_url' => env(key: 'SHOPTOK_BASE_URL', default: 'https://www.shoptok.si'),

    'categories' => [
        'televizorji'    => [
            'name' => 'Televizorji',
            'url'  => 'https://www.shoptok.si/televizorji/cene/206',
        ],
        'tv_sprejemniki' => [
            'name' => 'TV sprejemniki',
            'url'  => 'https://www.shoptok.si/tv-prijamnici/cene/56',
        ],
    ],

    'headers' => [
        // Modern Chrome 131
        'User-Agent'      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
        'Referer'         => 'https://www.shoptok.si/',
        'Accept'          => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
        'Accept-Language' => 'sl-SI,sl;q=0.9,en-GB;q=0.8,en;q=0.7',
    ],

    'throttle' => [
        'enabled' => env('CRAWLER_THROTTLE', true),
        'delay_us' => 1_500_000, // 1.5 seconds
    ],
];
