<?php

// Quick script to check students in database
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$students = \App\Models\Student::with('user')->limit(10)->get();

echo "Total Students: " . \App\Models\Student::count() . "\n\n";
echo "First 10 Students:\n";
echo str_repeat("-", 80) . "\n";
printf("%-20s %-30s %-15s\n", "Name", "Username", "Roll Number");
echo str_repeat("-", 80) . "\n";

foreach ($students as $student) {
    printf("%-20s %-30s %-15s\n", 
        $student->user->name, 
        $student->username, 
        $student->roll_number
    );
}

echo "\nType exact username to search (e.g., rahulsharma23): ";
$input = "rahulsharma23"; // Hardcoded for now
echo "Searching for '$input'...\n";

$student = \App\Models\Student::where('username', $input)->first();

if ($student) {
    echo "✅ FOUND! Student ID: {$student->id}, User ID: {$student->user_id}\n";
    echo "Associated User: " . ($student->user ? $student->user->name : "NULL") . "\n";
} else {
    echo "❌ NOT FOUND via Eloquent!\n";
    
    // Try raw SQL
    $raw = \Illuminate\Support\Facades\DB::select("SELECT * FROM students WHERE username = ?", [$input]);
    echo "Raw SQL Result: " . (count($raw) > 0 ? "FOUND" : "NOT FOUND") . "\n";
}
