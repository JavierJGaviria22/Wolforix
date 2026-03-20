<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Lead extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'name',
        'email',
        'phone',
        'interest',
        'status',
        'source'
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