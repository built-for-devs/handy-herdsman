<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * M0 seeds roles and all editable config data (§4, §7). Business records
     * (clients, cattle, bookings) are created through the app.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            RateConfigSeeder::class,
            ServiceSeeder::class,
            GestationConfigSeeder::class,
            ServiceAreaSeeder::class,
            AvailabilitySeeder::class,
        ]);
    }
}
