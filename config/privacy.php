<?php

return [
    'controller_name' => env('PRIVACY_CONTROLLER_NAME', config('app.name')),
    'controller_document' => env('PRIVACY_CONTROLLER_DOCUMENT'),
    'contact_email' => env('PRIVACY_CONTACT_EMAIL', env('MAIL_FROM_ADDRESS')),
    'officer_name' => env('PRIVACY_OFFICER_NAME'),
    'verification_hours' => (int) env('PRIVACY_VERIFICATION_HOURS', 24),
    'tracking_link_hours' => (int) env('PRIVACY_TRACKING_LINK_HOURS', 168),
    'unverified_retention_days' => (int) env('PRIVACY_UNVERIFIED_RETENTION_DAYS', 30),
    'request_retention_days' => (int) env('PRIVACY_REQUEST_RETENTION_DAYS', 730),
];
