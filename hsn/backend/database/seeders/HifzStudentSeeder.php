<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class HifzStudentSeeder extends Seeder
{
    public function run()
    {
        $rawData = <<<EOT
H220	MUHAMMAD HANI T V	H220	HIFZ
H221	MUHAMMED AFNAN	H221	HIFZ
H228	MUHAMMED HASAN PS	H228	HIFZ
H231	MUHAMMED MAFAZ ZAYAN P P	H231	HIFZ
H232	MUHAMMED RAZI MK	H232	HIFZ
H233	SAYYID MUHAMMED SAFIYY THANGAL	H233	HIFZ
H234	MUHAMMED SWABAH NK	H234	HIFZ
H236	SALMANUL FARIS P	H236	HIFZ
H237	MUHAMMED P	H237	HIFZ
H238	MUHAMMED AJSIN.V	H238	HIFZ
H241	MUHAMMAD NAZAL	H241	HIFZ
H242	ALI ZIYAN KV	H242	HIFZ
H243	MUHAMMED.E	H243	HIFZ
H244	MUHAMMED SHAHIN SHAN	H244	HIFZ
H247	MUHAMMED A	H247	HIFZ
H248	ABDUL NAFIH K	H248	HIFZ
H251	MUHAMMED ILAN	H251	HIFZ
H252	MUHAMMED TP	H252	HIFZ
H253	ADNAN PM	H253	HIFZ
H254	MISHAB PP	H254	HIFZ
H255	BISHRUL HAFI	H255	HIFZ
H256	MUHAMMED KP	H256	HIFZ
H258	MUHAMMED AYAN K N	H258	HIFZ
H259	MUHAMMED BILAL P	H259	HIFZ
H260	AHMED C P	H260	HIFZ
H261	MUHAMMED KP	H261	HIFZ
H262	MUHAMMAD M	H262	HIFZ
H263	MUHAMMAD AYAN	H263	HIFZ
H264	MIS-AB P	H264	HIFZ
H265	MUHAMMED P	H265	HIFZ
H266	MUHAMMAD FAZLUL HAQ.N	H266	HIFZ
H267	MUHAMMAD K	H267	HIFZ
H268	MUHAMMAD RAFEEQUE VP	H268	HIFZ
H269	MUHAMMED ZUHAN ZARIF PTP	H269	HIFZ
H270	MUHAMMED	H270	HIFZ
EOT;

        $lines = explode("\n", trim($rawData));

        // Get class names map if needed, or just assume IDs are correct as per user input
        // User inputs Class ID (last column).

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;

            $parts = preg_split('/\t+/', $line);
            
            // Format: [Adno/Roll] [Name] [Ignored] [Class Title]
            if (count($parts) < 3) continue; 

            $adno = trim($parts[0]);
            $name = trim($parts[1]);
            
            $classTitle = null;
            if (count($parts) >= 4) {
                 $classTitle = trim($parts[3]);
            } elseif (count($parts) === 3) {
                 // Fallback if 3rd column is missing or IS the class title?
                 // User format "WQQ WADI AL AQEEQ 12" -> 4 parts.
                 // "791 NAME NAME 1" -> 4 parts.
                 // If 3 parts, let's assume last is class title.
                 $classTitle = trim(end($parts));
            }

            if (!$classTitle) {
                $classTitle = 'Unknown Class';
            }

            // Create Class if not exists
            $classRoom = \App\Models\ClassRoom::firstOrCreate(
                ['name' => $classTitle],
                ['department' => 'hifz', 'total_points' => 0]
            );

            // Ensure unique email
            $email = preg_replace('/[^a-z0-9]/', '', strtolower($adno)) . '@hasanath.com';

            // Create/Find User
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($adno . '@portal'),
                    'role' => 'student',
                    'department' => 'hifz',
                ]
            );

            // Create/Update Student Profile
            Student::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'class_id' => $classRoom->id,
                    'username' => $adno,
                    'roll_number' => $adno,
                    'joined_at' => now(),
                    'total_points' => 0,
                    'wallet_balance' => 0,
                ]
            );
        }
    }
}
