<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('users')->insert([
            [
                'name' => 'Super Admin',
                'email' => 'super.admin@starter-kit.test',
                'password' => bcrypt('SuperSecret123!'),
                'reset_password' => false,
                'two_factor_type' => 'disabled',
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Test Admin',
                'email' => 'admin@example.com',
                'password' => bcrypt('TestPassword@123'),
                'reset_password' => false,
                'two_factor_type' => 'email',
                'created_by' => 2,
                'updated_by' => 2,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $user = User::where('email', 'super.admin@starter-kit.test')->first();
        $user->assignRole('Super Admin');

        $adminUser = User::where('email', 'admin@example.com')->first();
        if ($adminUser) {
            $adminUser->assignRole('Admin');
        }
    }
}
