<?php
// app/Models/PasswordOtp.php

namespace App\Models;
use Illuminate\Database\Eloquent\Model;


class PasswordOtp extends Model
{
    protected $fillable = ['email', 'otp', 'verified', 'expires_at'];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified' => 'boolean',
    ];
}