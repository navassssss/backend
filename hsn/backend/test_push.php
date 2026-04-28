<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$push = app(\App\Services\WebPushService::class);
echo "Broadcasting push notification...\n";
$push->broadcast([
    'title' => 'Test',
    'body' => 'Testing from CLI',
    'url' => '/'
]);
echo "Done.\n";
