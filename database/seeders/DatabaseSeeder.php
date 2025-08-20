<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🌱 Starting MP-Software Database Seeding...');
        
        // Call production RBAC seeder to set up roles, permissions, and default users
        $this->call([
            ProductionRBACSeeder::class,
        ]);
        
        $this->command->info('🎉 Database seeding completed successfully!');
        $this->command->warn('🔐 Remember to change default passwords in production environment!');

        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'Test User',
        //     'email' => 'test@example.com',
        // ]);
    }
}
