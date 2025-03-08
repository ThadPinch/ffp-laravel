<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PdfJob extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'design_id',
        'job_id',
        'type',
        'status',
        'file_path',
        'error_message',
    ];
    
    /**
     * Get the user that owns the PDF job.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the design associated with the PDF job.
     */
    public function design()
    {
        return $this->belongsTo(Design::class);
    }
}