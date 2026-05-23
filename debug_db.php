<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
echo ".env exists: " . (file_exists('.env') ? 'YES' : 'NO') . "\n";
echo "DB_CONNECTION: " . env('DB_CONNECTION') . "\n";
echo "DB_DATABASE: " . env('DB_DATABASE') . "\n";
