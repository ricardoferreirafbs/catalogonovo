<?php

return [
    'subject' => env('WEBPUSH_VAPID_SUBJECT', env('APP_URL')),
    'public_key' => env('WEBPUSH_VAPID_PUBLIC_KEY'),
    'private_key' => env('WEBPUSH_VAPID_PRIVATE_KEY'),
    'ttl' => (int) env('WEBPUSH_TTL', 300),
    'allowed_endpoint_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('WEBPUSH_ALLOWED_ENDPOINT_HOSTS', 'fcm.googleapis.com,updates.push.services.mozilla.com,web.push.apple.com,.notify.windows.com'))
    ))),
];
