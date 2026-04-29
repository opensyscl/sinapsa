<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workspace extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'slug', 'name', 'status', 'plan_code', 'billing_cycle',
        'trial_ends_at', 'current_period_ends_at',
        'meta_business_id', 'retention_days',
        'logo_url', 'contact_email',
    ];

    protected $casts = [
        'trial_ends_at' => 'datetime',
        'current_period_ends_at' => 'datetime',
        'retention_days' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing'
            && $this->trial_ends_at
            && $this->trial_ends_at->isFuture();
    }

    public function trialDaysLeft(): int
    {
        if (! $this->isTrialing()) {
            return 0;
        }
        return (int) max(0, now()->diffInDays($this->trial_ends_at, false));
    }
}
