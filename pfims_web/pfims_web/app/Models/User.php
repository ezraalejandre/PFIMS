<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Notifications\ApiResetPasswordNotification;

// Link records to the user table.
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Database table used by this model.
     *
     * @var string
     */
    protected $table = 'users';
    protected $attributes = ['status' => 'Active'];

    /**
     * Fields that can be saved together.
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
     * Fields to hide when returning this record.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'first_login_otp',
    ];

    /**
     * Set the data type for each field.
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

    // Link this record to its login histories.
    public function loginHistories()
    {
        return $this->hasMany(LoginHistory::class)->latest('logged_in_at');
    }

    // Link this record to its default filters.
    public function defaultFilters()
    {
        return $this->hasMany(UserDefaultFilter::class);
    }

//     public function sendPasswordResetNotification($token)
// {
//     $this->notify(new ApiResetPasswordNotification($token));
// }
}
