<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\LongArticle;

class LongArticleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 10 random long articles
        LongArticle::factory()->count(30)->create();
    }
}
