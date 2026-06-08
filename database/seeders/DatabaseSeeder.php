<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed shared AMIS users
        User::updateOrCreate(['email' => 'admin@amis.edu.ph'], [
            'name'             => 'AMIS Admin',
            'username'         => 'admin',
            'password'         => Hash::make('123'),
            'role'             => 'admin',
            'account_status'   => 'verified',
            'email_verified_at'=> now(),
        ]);

        User::updateOrCreate(['email' => 'student@amis.edu.ph'], [
            'name'             => 'AMIS Student',
            'username'         => 'student',
            'password'         => Hash::make('123'),
            'role'             => 'student',
            'account_status'   => 'verified',
            'email_verified_at'=> now(),
        ]);

        User::updateOrCreate(['email' => 'teacher@amis.edu.ph'], [
            'name'             => 'AMIS Teacher',
            'username'         => 'teacher',
            'password'         => Hash::make('123'),
            'role'             => 'teacher',
            'account_status'   => 'verified',
            'email_verified_at'=> now(),
        ]);
    }
}
