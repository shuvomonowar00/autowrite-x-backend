<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Client\Client;

class LongArticle extends Model
{
    use HasFactory;

    // Specify the table associated with the model
    protected $table = 'long_articles';

    // Specify the attributes that are mass assignable
    protected $fillable = [
        'client_id',
        'article_heading',
        'article_content',
        'article_type',
        'article_status',
        'publish_status',
        'gpt_version',
        'ai_generated_title',
        'article_language',
        'faqs',
    ];

    // Define the relationship with the PostPlatform model
    public function client()
    {
        return $this->belongsTo(Client::class);
    }
    public function platforms()
    {
        return $this->belongsToMany(PostPlatform::class, 'long_article_platforms')
            ->withPivot('post_url');
    }

    public function articlePlatforms()
    {
        return $this->hasMany(LongArticlePlatform::class, 'long_article_id');
    }
}
