<?php

namespace RankCrew\Laravel\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use RankCrew\Laravel\Models\BlogCategory;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'translation_group_id',
        'category_id',
        'language_code',
        'image',
        'title',
        'slug',
        'teaser',
        'body',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($post) {
            if (empty($post->slug)) {
                $slug = \Illuminate\Support\Str::slug($post->title);
                // Ensure uniqueness by checking if slug exists
                $count = static::where('slug', 'LIKE', "{$slug}%")->count();
                if ($count > 0) {
                    $slug .= '-' . ($count + 1);
                }
                $post->slug = $slug;
            }
        });
    }

    protected $with = ['category', 'user'];

    public function user()
    {
        return $this->belongsTo(config('auth.providers.users.model', 'App\Models\User'));
    }

    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    public function getImageAttribute($value)
    {
        if ($value && !filter_var($value, FILTER_VALIDATE_URL)) {
            return url($value);
        }
        return $value;
    }
}
