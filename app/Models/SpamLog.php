<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SpamLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'message_count',
        'window_start',
        'blocked'
    ];

    protected $casts = [
        'window_start' => 'datetime',
        'blocked' => 'boolean'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }
}