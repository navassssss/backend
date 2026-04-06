<?php

return [
    /*
    |--------------------------------------------------------------------------
    | VAPID Keys for Web Push
    |--------------------------------------------------------------------------
    | Generate keys by running: php generate_vapid_keys.php
    | Then add to .env:
    |   VAPID_PUBLIC_KEY=...
    |   VAPID_PRIVATE_KEY=...
    |   VAPID_SUBJECT=mailto:admin@dhic.edu
    */
    'vapid_public_key'  => env('VAPID_PUBLIC_KEY', ''),
    'vapid_private_key' => env('VAPID_PRIVATE_KEY', ''),
    'vapid_subject'     => env('VAPID_SUBJECT', 'mailto:admin@dhic.edu'),
];
