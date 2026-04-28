<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Test the search functionality
echo "Testing CCE Student Marks API with search...\n\n";

// Test 1: Get all students
echo "Test 1: All students (no filters)\n";
$response1 = file_get_contents('http://localhost:8000/api/cce/student-marks?page=1&per_page=10');
$data1 = json_decode($response1, true);
echo "Total students: " . ($data1['stats']['total_students'] ?? 'NO STATS') . "\n";
echo "Total subjects: " . ($data1['stats']['total_subjects'] ?? 'NO STATS') . "\n";
echo "Students in response: " . count($data1['data'] ?? []) . "\n\n";

// Test 2: Search for "445"
echo "Test 2: Search for '445'\n";
$response2 = file_get_contents('http://localhost:8000/api/cce/student-marks?page=1&per_page=10&search=445');
$data2 = json_decode($response2, true);
echo "Total students: " . ($data2['stats']['total_students'] ?? 'NO STATS') . "\n";
echo "Total subjects: " . ($data2['stats']['total_subjects'] ?? 'NO STATS') . "\n";
echo "Students in response: " . count($data2['data'] ?? []) . "\n";
if (isset($data2['data'][0])) {
    echo "First student: " . $data2['data'][0]['studentName'] . " (Roll: " . $data2['data'][0]['rollNumber'] . ")\n";
}
echo "\n";

// Test 3: Filter by class
echo "Test 3: Filter by class_id=9\n";
$response3 = file_get_contents('http://localhost:8000/api/cce/student-marks?page=1&per_page=10&class_id=9');
$data3 = json_decode($response3, true);
echo "Total students: " . ($data3['stats']['total_students'] ?? 'NO STATS') . "\n";
echo "Total subjects: " . ($data3['stats']['total_subjects'] ?? 'NO STATS') . "\n";
echo "Students in response: " . count($data3['data'] ?? []) . "\n\n";

echo "Done!\n";
