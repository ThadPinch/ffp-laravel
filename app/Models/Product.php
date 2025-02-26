<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'name',
        'finished_width',
        'finished_length',
        'per_piece_weight',
        'description',
        'production_product_id',
        'product_image',
    ];

    protected $casts = [
        'finished_width' => 'decimal:2',
        'finished_length' => 'decimal:2',
        'per_piece_weight' => 'decimal:2',
    ];

    // Helper method to get dimensions in pixels at 300 DPI
    public function getDimensionsAtDpi($dpi = 300)
    {
        return [
            'width' => $this->finished_width * $dpi,
            'height' => $this->finished_length * $dpi,
        ];
    }
}