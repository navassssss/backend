<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$student = \App\Models\Student::whereHas('user', function($q) {
    $q->where('name', 'like', '%Sayyid Jusaid%');
})->with('user')->first();

if ($student) {
    echo "Student ID: " . $student->id . "\n";
    
    // Check allocations
    $allocs = \App\Models\FeePaymentAllocation::where('student_id', $student->id)->get();
    echo "Allocations:\n";
    foreach ($allocs as $a) {
        echo " - Payment {$a->fee_payment_id} -> {$a->month}/{$a->year}: {$a->allocated_amount}\n";
    }
} else {
    echo "Student not found\n";
}
