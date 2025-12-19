<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => "SAYYID ALI BA'ALAWI THANGAL",
            'email' => 'sayyid.thangal@gmail.com',
            'role' => 'principal',
            'department' => 'Quran & Sunnah (D)',
            'phone' => '7560853923',
            'password' => bcrypt('principal@123')
        ]);

        User::create([
            'name' => "ANAS HUDAWI ARIPRA",
            'email' => 'anas.aripra@gmail.com',
            'role' => 'manager',
            'department' => 'Quran & Sunnah',
            'phone' => '8086762303',
            'password' => bcrypt('manager@123')
        ]);

        User::create([
            'name' => "ABDUL AZEEZ BAQAWI PUKAYOOR",
            'email' => 'azeez.pukayoor@gmail.com',
            'role' => 'teacher',
            'department' => 'Fiqh & U.Fiqh (D)',
            'phone' => '9539177688',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "HYDER ALI HUDAWI KUMBIDI",
            'email' => 'hyder.kumbidi@gmail.com',
            'role' => 'manager',
            'phone' => '9747619659',
            'password' => bcrypt('manager@123')
        ]);

        User::create([
            'name' => "UMARUL FAROOQ HUDAWI IRITTY",
            'email' => 'umar.farooq@gmail.com',
            'role' => 'teacher',
            'department' => 'Arabic Language (D)',
            'phone' => '9747544962',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "UNAIS HUDAWI VELIMUKKU",
            'email' => 'unais.velimukku@gmail.com',
            'role' => 'teacher',
            'department' => 'Islamic History',
            'phone' => '9995391295',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "MAJEED HUDAWI WAYANAD",
            'email' => 'majeed.wayanad@gmail.com',
            'role' => 'teacher',
            'department' => 'Fiqh & Tasawwuf',
            'phone' => '9656254680',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "SALMAN P HUDAWI KUDALLUR",
            'email' => 'salman.kudallur@gmail.com',
            'role' => 'teacher',
            'department' => 'History (D)',
            'phone' => '9207836161',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "UMAR ABDULLAH HUDAWI KOTTIKKULAM",
            'email' => 'umar.kottikkulam@gmail.com',
            'role' => 'teacher',
            'department' => 'Urdu Language',
            'phone' => '9995938120',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "YASIR HUDAWI PUKAYUR",
            'email' => 'yasir.pukayur@gmail.com',
            'role' => 'teacher',
            'department' => 'English (D)',
            'phone' => '8943759739',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "SHAHEER HUDAWI MAYINMUKKU",
            'email' => 'shaheer.mayinmukku@gmail.com',
            'role' => 'teacher',
            'department' => 'T & C',
            'phone' => '9567016152',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "SUHAIL MASTER CHERUVATHALA",
            'email' => 'suhail.cheruvathala@gmail.com',
            'role' => 'teacher',
            'department' => 'Social Science & History',
            'phone' => '9995310409',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "HAFIZ UZAIR QASIMI BAGALPUR",
            'email' => 'uzair.bagalpur@gmail.com',
            'role' => 'teacher',
            'department' => 'Urdu & Literature',
            'phone' => '9995430689',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "ASIF BAQAWI CHERUKUNNU",
            'email' => 'asif.cherukunnu@gmail.com',
            'role' => 'teacher',
            'department' => 'Aqeedah & Manthiq',
            'phone' => '8075210308',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "ALI JOUHAR HUDAWI KOTTAKKAL",
            'email' => 'ali.jouhar@gmail.com',
            'role' => 'teacher',
            'department' => 'Social Science (D)',
            'phone' => '9645356189',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "ASHIQ WAFY MAMBAD",
            'email' => 'ashiq.mambad@gmail.com',
            'role' => 'teacher',
            'department' => 'Science',
            'phone' => '8589916700',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "Dr. ISMAEEL HUDAWI CHEMMALASSERY",
            'email' => 'ismaeel.chemmalassery@gmail.com',
            'role' => 'manager',
            'department' => 'Research & Presentation',
            'password' => bcrypt('manager@123')
        ]);

        User::create([
            'name' => "HAFIZ ASHRAF HUDAWI PANNIYUR",
            'email' => 'ashraf.panniyur@gmail.com',
            'role' => 'teacher',
            'department' => 'Adab & Balaga',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "SHAMLAN HUDAWI PEDENA",
            'email' => 'shamlan.pedena@gmail.com',
            'role' => 'teacher',
            'department' => 'Art & Designing',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "ANSHIF SHAHEEN HUDAWI PERINTHALMANNA",
            'email' => 'anshif.shaheen@gmail.com',
            'role' => 'teacher',
            'department' => 'Speech & Presentation',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "HAFIZ SWALIH HUDAWI OORAKAM",
            'email' => 'swalih.oorakam@gmail.com',
            'role' => 'teacher',
            'department' => 'English',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "MUHAMMED RASHAD HUDAWI TANALUR",
            'email' => 'rashad.tanalur@gmail.com',
            'role' => 'teacher',
            'department' => 'Library & Reading',
            'phone' => '8891639836',
            'password' => bcrypt('teacher@123')
        ]);

        User::create([
            'name' => "ABDUL QADAR HUDAWI TRIPPANACHY",
            'email' => 'qadar.trippanachy@gmail.com',
            'role' => 'teacher',
            'department' => 'IT & Studio',
            'password' => bcrypt('teacher@123')
        ]);
    }
}
