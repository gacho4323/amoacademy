<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin user
        User::factory()->admin()->create([
            'name' => 'Admin User',
            'email' => 'admin@gmail.com',
            'password' => \Illuminate\Support\Facades\Hash::make('Admin123@'),
        ]);

        // Create regular user
        User::factory()->user()->create([
            'name' => 'Regular User',
            'email' => 'user@gmail.com',
            'password' => \Illuminate\Support\Facades\Hash::make('User123@'),
        ]);

        Course::factory()->count(5)->create();
    }
}