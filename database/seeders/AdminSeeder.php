<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Admin;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::create([
            'name' => 'Super Admin',
            'email' => 'admin@afrikticket.com',
            'password' => Hash::make('Admin@123'),
            'role' => 'admin',
            'status' => 'active',
            'phone' => '+1234567890'
        ]);

        Admin::create([
            'user_id' => $user->id,
            'role' => 'super_admin',
            'permissions' => ['users', 'organizations', 'events', 'fundraising', 'settings']
        ]);
    }
}