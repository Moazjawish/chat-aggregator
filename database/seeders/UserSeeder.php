<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            [
                'email' => 'admin@example.com',
            ],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
            ]
        );

        User::updateOrCreate(
            [
                'email' => 'user@example.com',
            ],
            [
                'name' => 'Test User',
                'password' => Hash::make('user123@'),
            ]
        );


    }
}
