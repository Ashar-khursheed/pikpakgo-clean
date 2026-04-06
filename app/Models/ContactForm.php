<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactForm extends Model
{
    protected $fillable = [
        'name', 'email', 'phone', 'subject', 'message',
        'type', 'booking_reference', 'status',
        'admin_reply', 'replied_at', 'ip_address',
    ];

    protected $casts = ['replied_at' => 'datetime'];
}
