<?php

namespace Database\Factories;

use App\Models\LongArticle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\LongArticle>
 */
class LongArticleFactory extends Factory
{
    protected $model = LongArticle::class;

    /**
     * Define the model's default state.
     *
     * @return array
     */
    public function definition()
    {
        return [
            'article_heading' => $this->faker->sentence,
            'article_content' => json_encode([
                'introduction' => $this->faker->paragraph,
                'body' => [
                    'part1' => $this->faker->paragraph,
                    'part2' => $this->faker->paragraph,
                    'part3' => $this->faker->paragraph
                ],
                'conclusion' => $this->faker->paragraph
            ]),
            'created_at' => now(),
            'updated_at' => now()
        ];
    }
}
