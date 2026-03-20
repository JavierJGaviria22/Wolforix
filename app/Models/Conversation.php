<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Conversation extends Model
{
    use HasFactory;

    protected $fillable = [
        'contact_id',
        'status',
        'last_message_at'
    ];

    protected $casts = [
        'last_message_at' => 'datetime',
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

    public function messages()
    {
        return $this->hasMany(Message::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function lastMessages($limit = 10)
    {
        return $this->messages()
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->reverse();
    }
}