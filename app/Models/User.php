<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * 👤 **User Model**
 *
 * Represents a registered user of your Laravel application.
 *
 * 🧠 Think of this as the “account record”:
 * - It stores login credentials (email, password).
 * - It integrates with Laravel’s authentication system.
 * - It can receive notifications (like password reset or verification emails).
 *
 * **Why it exists:**
 * - Laravel expects a `User` model for authentication (`Auth::user()`).
 * - Even if this project doesn’t use user-facing auth yet, it keeps the app ready for it.
 *
 */
class User extends Authenticatable
{
    /**
     * 🧩 Traits
     *
     * - {@see HasFactory}: enables factory-based seeding and testing.
     * - {@see Notifiable}: allows sending notifications (emails, etc).
     *
     * @use HasFactory<UserFactory>
     */
    use HasFactory, Notifiable;

    /**
     * 🧱 The attributes that can be safely mass assigned.
     *
     * These are the fields you can pass into `User::create([...])`.
     *
     * @var list<string>
     *
     * Example:
     * ```
     * User::create([
     *   'name' => 'Dusan',
     *   'email' => 'test@example.com',
     *   'password' => Hash::make('secret'),
     * ]);
     * ```
     */
    protected $fillable
        = [
            'name',
            'email',
            'password',
        ];

    /**
     * 🔒 The attributes that should be hidden when serialized (e.g. to JSON).
     *
     * Prevents leaking sensitive information like passwords or tokens.
     *
     * @var list<string>
     */
    protected $hidden
        = [
            'password',
            'remember_token',
        ];

    /**
     * 🧮 Type casting configuration.
     *
     * Ensures fields have proper PHP types when accessed:
     * - `email_verified_at` → `Carbon\Carbon` instance
     * - `password` → automatically hashed (via “hashed” cast)
     *
     * @return array<string, string>
     */
    protected function casts() : array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
