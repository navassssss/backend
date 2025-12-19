<?php

namespace Database\Seeders;

use App\Models\Duty;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DutySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get a valid creator (Principal or Admin)
        $creatorUser = User::where('role', 'principal')->first() ?? User::first();
        $creator = $creatorUser ? $creatorUser->id : 1;

        // Helper: create duty
        function addDuty($name, $description, $frequency, $creator) {
            return Duty::firstOrCreate(
                ['name' => $name], // Check by name to avoid duplicates
                [
                    'description' => $description,
                    'type' => 'responsibility',
                    'frequency' => $frequency,
                    'created_by' => $creator
                ]
            );
        }

        // Helper: assign duty to teacher
        function giveDuty($teacherName, $dutyId, $creator) {
            $teacher = User::where('name', $teacherName)->first();
            if($teacher) {
                // Check assuming uniqueness
                if (!DB::table('duty_teacher')->where('duty_id', $dutyId)->where('teacher_id', $teacher->id)->exists()) {
                    DB::table('duty_teacher')->insert([
                        'duty_id' => $dutyId,
                        'teacher_id' => $teacher->id,
                        'assigned_by' => $creator,
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);
                }
            } else {
                echo "Warning: Teacher not found - $teacherName\n";
            }
        }

        /* ===========================================
           1. CREATE DUTIES
        =========================================== */

        $principal = addDuty(
            "Principal – Institutional Supervision",
            "Oversees academic, administrative and institutional performance.",
            "monthly",
            $creator
        );

        $vicePrincipal = addDuty(
            "Vice Principal – Academic Monitoring",
            "Assists principal and supervises academic quality.",
            "weekly",
            $creator
        );

        $moralAdvisor = addDuty(
            "Moral & Discipline Guidance",
            "Provides spiritual guidance and monitors moral discipline.",
            "weekly",
            $creator
        );

        $administration = addDuty(
            "Administration Management",
            "Handles institutional administration, documentation, and staff coordination.",
            "daily",
            $creator
        );

        $staffSecretary = addDuty(
            "Staff Coordination & Viva",
            "Staff communication, SWC management and viva arrangement.",
            "weekly",
            $creator
        );

        $classTeacherDaily = addDuty(
            "Class Teacher Duty",
            "Daily class supervision, attendance, discipline, and parent communication.",
            "daily",
            $creator
        );

        $examManagement = addDuty(
            "Exam Management",
            "Exam preparation, monitoring and reporting.",
            "weekly",
            $creator
        );

        $academicStore = addDuty(
            "Academic Store Management",
            "Maintains and tracks academic materials.",
            "monthly",
            $creator
        );

        $hodDepartment = addDuty(
            "Department Head Responsibility",
            "Supervises academic department and ensures curriculum quality.",
            "weekly",
            $creator
        );

        $libraryDuty = addDuty(
            "Library & Reading Supervision",
            "Manages library, reading programs and issuing books.",
            "daily",
            $creator
        );

        $itStudioDuty = addDuty(
            "IT & Studio Operations",
            "Manages IT systems, studio recordings and program archiving.",
            "weekly",
            $creator
        );

        $hizbDawra = addDuty(
            "Hizb & Dawra Supervision",
            "Monitors memorization students and Dawra sessions.",
            "weekly",
            $creator
        );

        $ogeaArt = addDuty(
            "OGEA, Art & Hygiene",
            "Manages art sessions and ensures hygiene standards.",
            "weekly",
            $creator
        );

        /* ===========================================
           2. ASSIGN DUTIES TO TEACHERS
        =========================================== */

        // Principal
        giveDuty("SAYYID ALI BA'ALAWI THANGAL", $principal->id, $creator);

        // Vice Principal
        giveDuty("ANAS HUDAWI ARIPRA", $vicePrincipal->id, $creator);

        // Moral Advisor
        giveDuty("ABDUL AZEEZ BAQAWI PUKAYOOR", $moralAdvisor->id, $creator);

        // Administrator
        giveDuty("HYDER ALI HUDAWI KUMBIDI", $administration->id, $creator);

        // Staff Secretary
        giveDuty("UMARUL FAROOQ HUDAWI IRITTY", $staffSecretary->id, $creator);

        // Class Teachers (Daily Duty)
        $classTeachers = [
            "UNAIS HUDAWI VELIMUKKU",
            "MAJEED HUDAWI WAYANAD",
            "SALMAN P HUDAWI KUDALLUR",
            "UMAR ABDULLAH HUDAWI KOTTIKKULAM",
            "YASIR HUDAWI PUKAYUR",
            "SHAHEER HUDAWI MAYINMUKKU",
            "ALI JOUHAR HUDAWI KOTTAKKAL",
            "ASHIQ WAFY MAMBAD",
            "HAFIZ ASHRAF HUDAWI PANNIYUR",
            "ANSHIF SHAHEEN HUDAWI PERINTHALMANNA"
        ];

        foreach ($classTeachers as $teacherName) {
            giveDuty($teacherName, $classTeacherDaily->id, $creator);
        }

        // Exam Duty
        giveDuty("SUHAIL MASTER CHERUVATHALA", $examManagement->id, $creator);

        // Academic Store Duty
        giveDuty("UNAIS HUDAWI VELIMUKKU", $academicStore->id, $creator);

        // HoD duties
        $hodTeachers = [
            "SALMAN P HUDAWI KUDALLUR",
            "UMAR ABDULLAH HUDAWI KOTTIKKULAM",
            "YASIR HUDAWI PUKAYUR"
        ];
        foreach($hodTeachers as $t) {
            giveDuty($t, $hodDepartment->id, $creator);
        }

        // Library
        giveDuty("MUHAMMED RASHAD HUDAWI TANALUR", $libraryDuty->id, $creator);

        // IT & Studio
        giveDuty("ABDUL QADAR HUDAWI TRIPPANACHY", $itStudioDuty->id, $creator);

        // Hizb & Dawra
        $hizbTeachers = [
            "HAFIZ UZAIR QASIMI BAGALPUR",
            "HAFIZ SWALIH HUDAWI OORAKAM"
        ];
        foreach ($hizbTeachers as $t) {
            giveDuty($t, $hizbDawra->id, $creator);
        }

        // OGEA, Art & Hygiene
        giveDuty("SHAMLAN HUDAWI PEDENA", $ogeaArt->id, $creator);
    }
}
