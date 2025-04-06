<?php

namespace Database\Factories;

use App\Models\LongArticle;
use App\Models\PostPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

class LongArticlePlatformFactory extends Factory
{
    /**
     * Summary of definition
     * @return array
     */
    public function definition(): array
    {
        return [
            'long_article_id' => LongArticle::factory(),
            'post_platform_id' => PostPlatform::factory(),
        ];
    }
}
