<?php

namespace Database\Seeders;

use App\Models\StoreSetting;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * @throws RuntimeException
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('Database seeding is refused in production.');
        }

        StoreSetting::current();
    }
}
