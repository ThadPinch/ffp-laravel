<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DesignVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'design_id',
        'elements',
        'thumbnail',
        'comment',
    ];

    protected $casts = [
        'elements' => 'array',
    ];

    public function design()
    {
        return $this->belongsTo(Design::class);
    }
}