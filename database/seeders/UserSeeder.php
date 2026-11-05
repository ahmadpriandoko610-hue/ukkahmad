<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'name' => 'Administrator',
            'email' => 'admin@rapi.com',
            'password' => Hash::make('admin123'),
            'role' => 'admin',
        ]);

        User::create([
            'name' => 'PIC',
            'email' => 'pic@rapi.com',
            'password' => Hash::make('pic123'),
            'role' => 'pic',
        ]);

        User::create([
            'name' => 'Guru',
            'email' => 'guru@rapi.com',
            'password' => Hash::make('guru123'),
            'role' => 'guru',
        ]);
    }
}