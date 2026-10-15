<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dummy data only. There is no sign up flow yet, so this is where schools come from.
     */
    public function run(): void
    {
        $this->call([
            SchoolSeeder::class,
            FundraisingRateSeeder::class,
        ]);

        if (! app()->isProduction()) {
            $this->call(DemoOrderSeeder::class);
        }
    }
}
