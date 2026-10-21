<?php

namespace App\Models;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Enums\UserType;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $ulid
 * @property UserType $type
 * @property string $first_name
 * @property string $last_name
 * @property-read string $name
 * @property string $email
 * @property string|null $mobile
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $terms_version
 * @property Carbon|null $terms_accepted_at
 * @property Carbon|null $popia_consent_at
 * @property Carbon|null $last_login_at
 * @property string|null $last_login_ip
 * @property Carbon|null $deactivated_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['first_name', 'last_name', 'email', 'mobile', 'password'])]
#[Hidden(['id', 'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /** @var list<string> */
    protected $appends = ['name'];

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            $user->ulid ??= (string) Str::ulid();
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => UserType::class,
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'popia_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'ulid';
    }

    /** @return Attribute<string, never> */
    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    public function isStaff(): bool
    {
        return $this->type === UserType::Staff;
    }

    public function isBuyer(): bool
    {
        return $this->type === UserType::Buyer;
    }

    public function isActive(): bool
    {
        return $this->deactivated_at === null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole(SystemRole::SuperAdmin->value);
    }

    public function hasConfirmedTwoFactor(): bool
    {
        return $this->two_factor_secret !== null && $this->two_factor_confirmed_at !== null;
    }

    /**
     * @param  Builder<User>  $query
     */
    public function scopeStaff(Builder $query): void
    {
        $query->where('type', UserType::Staff);
    }
}
