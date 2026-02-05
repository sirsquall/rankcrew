<?php

namespace RankCrew\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class BlogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    public function blogPosts()
    {
        return $this->hasMany(BlogPost::class, 'category_id');
    }
}
