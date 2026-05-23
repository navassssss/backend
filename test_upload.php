<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

file_put_contents('test.txt', 'hello');
$file = new UploadedFile('test.txt', 'test.txt', 'text/plain', null, true);

try {
    $path = $file->store('achievements', 'public');
    echo "store() returned: " . var_export($path, true) . "\n";
    
    $path2 = Storage::disk('public')->putFile('achievements', $file);
    echo "putFile() returned: " . var_export($path2, true) . "\n";
} catch (\Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}
