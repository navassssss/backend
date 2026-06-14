<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Student;
use App\Models\Department;

class StudentDepartmentSeeder extends Seeder
{
    public function run()
    {
        $civilRollNumbers = [
            '401', '426', '438', '442', '736', '749', '785', '400', '393', '445', 
            '454', '430', '746', '456', '428', '747', '429', '441', '415', '414', 
            '394', '477', '794', '797', '466', '796', '492', '440', '486', '465', 
            '793', '479', '413', '839', '504', '470', '496', '468', '795', '532', 
            '527', '514', '734', '523', '540', '533', '485', '516', '536', '503', 
            '530', '488', '511', '741', '519', '525', '529', '522'
        ];

        $hadithRollNumbers = [
            '439', '444', '391', '459', '745', '748', '425', '750', '435', '423', 
            '437', '455', '457', '432', '481', '473', '474', '471', '484', '802', 
            '446', '495', '475', '482', '497', '799', '499', '800', '801', '458', 
            '427', '847', '844', '845', '884', '478', '883', '515', '512', '846', 
            '513', '843', '521', '842', '467', '848'
        ];

        $failedStudents = [];
        $successCount = 0;

        $this->command->info('Starting Student Department Seeder...');

        // Create or get departments
        $civilDept = Department::firstOrCreate(['name' => 'Civilizational Studies']);
        $hadithDept = Department::firstOrCreate(['name' => 'Hadith & Related Sciences']);

        // 1. Process Civilizational Studies
        $this->command->info('Processing Civilizational Studies students...');
        foreach ($civilRollNumbers as $rollNumber) {
            $student = Student::where('roll_number', $rollNumber)->first();
            if ($student) {
                $student->department_id = $civilDept->id;
                $student->save();
                $successCount++;
            } else {
                $this->command->warn("Student with Roll Number {$rollNumber} not found.");
                $failedStudents[] = [
                    'roll_number' => $rollNumber,
                    'department' => 'Civilizational Studies'
                ];
            }
        }

        // 2. Process Hadith & Related Sciences
        $this->command->info('Processing Hadith & Related Sciences students...');
        foreach ($hadithRollNumbers as $rollNumber) {
            $student = Student::where('roll_number', $rollNumber)->first();
            if ($student) {
                $student->department_id = $hadithDept->id;
                $student->save();
                $successCount++;
            } else {
                $this->command->warn("Student with Roll Number {$rollNumber} not found.");
                $failedStudents[] = [
                    'roll_number' => $rollNumber,
                    'department' => 'Hadith & Related Sciences'
                ];
            }
        }

        // 3. Summary Report
        $this->command->info("\n--- Execution Summary ---");
        $this->command->info("Successfully assigned: {$successCount} students.");

        if (count($failedStudents) > 0) {
            $this->command->error("\n--- List of Failed / Not Found Students (" . count($failedStudents) . ") ---");
            foreach ($failedStudents as $fail) {
                $this->command->line("Roll Number: {$fail['roll_number']} (Target Dept: {$fail['department']})");
            }
        } else {
            $this->command->info("All students processed successfully! No failures.");
        }
    }
}
