<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin already exists
        $adminExists = User::where('email', 'admin@pikpakgo.com')->exists();

        if (!$adminExists) {
            User::create([
                'first_name' => 'Admin',
                'last_name' => 'PikPakGo',
                'email' => 'admin@pikpakgo.com',
                'password' => Hash::make('Admin@123456'), // Change this password!
                'user_type' => 'admin',
                'status' => 'active',
                'email_verified_at' => now(),
                'country' => 'USA',
                'preferred_currency' => 'USD',
                'preferred_language' => 'en',
            ]);

            $this->command->info('Admin user created successfully!');
            $this->command->info('Email: admin@pikpakgo.com');
            $this->command->info('Password: Admin@123456');
            $this->command->warn('IMPORTANT: Change the password after first login!');
        } else {
            $this->command->info('Admin user already exists.');
        }
    }
}