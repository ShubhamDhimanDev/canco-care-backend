<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * No user is created here on purpose: a seeded account with a known password
     * would be a back door on any server where this runs. Create the first
     * administrator with `php artisan make:admin`.
     */
    public function run(): void
    {
        //
    }
}
