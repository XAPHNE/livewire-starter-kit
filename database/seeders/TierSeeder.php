<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tier;

class TierSeeder extends Seeder
{
    public function run(): void
    {
        $tiers = [
            ['name' => 'Free', 'description' => 'Free tier — basic access', 'concurrent_sessions' => 1],
            ['name' => 'Standard', 'description' => 'Standard tier — small teams', 'concurrent_sessions' => 3],
            ['name' => 'Pro', 'description' => 'Pro tier — power users', 'concurrent_sessions' => 10],
        ];

        foreach ($tiers as $t) {
            Tier::updateOrCreate(['name' => $t['name']], $t);
        }
    }
}
