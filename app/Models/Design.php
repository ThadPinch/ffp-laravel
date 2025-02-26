<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Design extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'product_id',
        'elements',
        'thumbnail',
        'name',
        'is_template',
    ];

    protected $casts = [
        'elements' => 'array',
        'is_template' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function versions()
    {
        return $this->hasMany(DesignVersion::class);
    }

    // Create a new version of this design
    public function createVersion($comment = null)
    {
        return $this->versions()->create([
            'elements' => $this->elements,
            'thumbnail' => $this->thumbnail,
            'comment' => $comment,
        ]);
    }
}