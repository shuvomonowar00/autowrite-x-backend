<?php

namespace Database\Seeders;

use App\Models\PostPlatform;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PostPlatformSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        PostPlatform::factory()->count(1)->create();
    }
}
