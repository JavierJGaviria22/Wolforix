<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Message extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'contact_id',
        'direction',
        'message_type',
        'content',
        'media_url',
        'whatsapp_message_id',
        'is_ai'
    ];

    protected $casts = [
        'is_ai' => 'boolean'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function conversation()
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact()
    {
        return $this->belongsTo(Contact::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeIncoming($query)
    {
        return $query->where('direction', 'incoming');
    }

    public function scopeOutgoing($query)
    {
        return $query->where('direction', 'outgoing');
    }

    public function scopeAI($query)
    {
        return $query->where('is_ai', true);
    }
}