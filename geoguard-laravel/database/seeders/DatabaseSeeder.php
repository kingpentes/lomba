<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

use App\Models\MonitoringNode;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Admin User
        User::firstOrCreate(
            ['email' => 'admin@geoguard.local'],
            [
                'name' => 'Admin GeoGuard',
                'password' => bcrypt('password'),
            ]
        );

        // Default Monitoring Node
        MonitoringNode::firstOrCreate(
            ['node_code' => 'INC_HW_01'],
            [
                'name' => 'Pit 1 Highwall Inclinometer',
                'latitude' => -1.237,
                'longitude' => 116.852,
                'elevation' => 85.0,
                'status' => 'STABLE',
            ]
        );
    }
}
