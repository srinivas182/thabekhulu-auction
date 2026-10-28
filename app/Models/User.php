<?php

namespace App\Models;

use App\Domains\Access\Enums\SystemRole;
use App\Domains\Identity\Enums\UserType;
use App\Domains\Identity\Notifications\QueuedVerifyEmail;
use App\Domains\Platform\Email\EmailConfig;
use App\Domains\Settings\Settings\SecuritySettings;
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
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
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
    use HasFactory, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

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
            'whatsapp_opt_in_at' => 'datetime',
            'attribution' => 'array',
            'password' => 'hashed',
            'terms_accepted_at' => 'datetime',
            'popia_consent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'deactivated_at' => 'datetime',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    /**
     * Profile and status changes go to the audit log. Passwords and 2FA
     * secrets are never logged; sign-in timestamps are saved quietly.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('users')
            ->logOnly(['first_name', 'last_name', 'email', 'mobile', 'type', 'email_verified_at', 'deactivated_at', 'two_factor_confirmed_at'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn (string $event): string => match ($event) {
                'created' => 'Account created',
                'updated' => 'Account details changed',
                'deleted' => 'Account deleted',
                default => ucfirst($event),
            });
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

    public function isSeller(): bool
    {
        return $this->type === UserType::Seller;
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

    /** Sent in the background, so a mail-server problem can't break registration. */
    public function sendEmailVerificationNotification(): void
    {
        if (self::emailVerificationRequired()) {
            $this->notify(new QueuedVerifyEmail);
        }
    }

    /** Settings » Security » "Buyers must verify their email address" (on by default; off only for testing). */
    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null || ! self::emailVerificationRequired();
    }

    private static function emailVerificationRequired(): bool
    {
        // Never require verification while no email can be sent (Back office » Email provider).
        return app(SecuritySettings::class)->email_verification_required && EmailConfig::sending();
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

    /** Private alerts (outbid) for this buyer only; see routes/channels.php. */
    public function receivesBroadcastNotificationsOn(): string
    {
        return 'bidder.'.$this->ulid; // the browser only knows the ulid; internal ids never leave the server
    }
}
