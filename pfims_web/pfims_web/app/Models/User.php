<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ApiResetPasswordNotification;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'users';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone',
        'location',
        'status',
        'profile_photo',
        'first_login_verification_required',
        'first_login_otp',
        'first_login_otp_expires_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'first_login_otp',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'first_login_verification_required' => 'boolean',
            'first_login_otp_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Check if user has a given role.
     */
    public function isRole(string $role): bool
    {
        return isset($this->role) && $this->role === $role;
    }

//     public function sendPasswordResetNotification($token)
// {
//     $this->notify(new ApiResetPasswordNotification($token));
// }
}
