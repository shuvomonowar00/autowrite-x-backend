<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LongArticlePlatform extends Model
{
    use HasFactory;

    protected $table = 'long_article_platforms';

    protected $fillable = [
        'long_article_id',
        'post_platform_id',
        'post_url',
    ];

    public function article()
    {
        return $this->belongsTo(LongArticle::class, 'long_article_id');
    }

    public function platform()
    {
        return $this->belongsTo(PostPlatform::class, 'post_platform_id');
    }
}
