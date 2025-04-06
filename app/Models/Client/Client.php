<?php

namespace App\Models\Client;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use App\Notifications\Client\ResetPasswordNotification;

class Client extends Authenticatable implements MustVerifyEmail, CanResetPasswordContract
{
    use HasApiTokens, HasFactory, Notifiable, CanResetPassword;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'profile_photo',
        'username',
        'email',
        'password',
        'verification_token',
        'verification_deadline',
        'google_id',
        'google_avatar',
        'facebook_id',
        'facebook_avatar',
        'email_verified_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'google_id',         // Hide for security
        'verification_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'verification_deadline' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * Get the client's full name.
     */
    public function getFullNameAttribute(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    /**
     * Check if user registered with Google
     */
    public function isGoogleUser(): bool
    {
        return !empty($this->google_id);
    }

    /**
     * Get the profile photo URL
     */
    public function getProfilePhotoUrlAttribute(): string
    {
        if ($this->profile_photo === 'google_avatar' && $this->google_avatar) {
            return $this->google_avatar;
        }

        return $this->profile_photo
            ? asset('storage/profile_photos/' . $this->profile_photo)
            : asset('images/default-profile.png');
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        // Don't send reset emails to Google users if they haven't set a password
        if ($this->isGoogleUser() && $this->password === null) {
            return;
        }

        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Check if the email needs verification
     */
    public function needsEmailVerification(): bool
    {
        // Google-authenticated users don't need email verification
        if ($this->isGoogleUser()) {
            return false;
        }

        return $this->email_verified_at === null;
    }

    /**
     * Articles relationship
     */
    public function articles()
    {
        return $this->hasMany(\App\Models\LongArticle::class);
    }
}
