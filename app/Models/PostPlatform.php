<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PostPlatform extends Model
{
    use HasFactory;

    // Specify the table associated with the model
    protected $table = 'post_platforms';

    // Specify the attributes that are mass assignable
    protected $fillable = ['platform_name'];

    // Define the relationship with the LongArticle model
    public function articles()
    {
        return $this->belongsToMany(LongArticle::class, 'long_article_platforms');
    }
}
