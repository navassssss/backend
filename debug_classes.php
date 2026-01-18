<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$classes = App\Models\ClassRoom::select('id', 'name')->get()->map(function($c) {
    return [
        'id' => $c->id,
        'name' => $c->name,
        'studentCount' => $c->students()->count()
    ];
});

echo json_encode($classes);
