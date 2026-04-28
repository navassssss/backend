<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "Total Subscriptions: " . \App\Models\PushSubscription::count() . "\n";
foreach (\App\Models\PushSubscription::with('user:id,name')->get() as $sub) {
    echo "- User: {$sub->user->name} (ID: {$sub->user_id}) | Endpoint: " . substr($sub->endpoint, 0, 50) . "...\n";
}
