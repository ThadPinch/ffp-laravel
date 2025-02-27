<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'design_id',
        'user_id',
        'role', // 'user' or 'assistant'
        'content',
        'metadata'
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    // Relationships
    public function design()
    {
        return $this->belongsTo(Design::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}