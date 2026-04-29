<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Contact extends Model
{
    use BelongsToWorkspace, HasFactory, SoftDeletes;

    protected $fillable = [
        'workspace_id', 'name', 'phone', 'email', 'avatar_url',
        'identifiers', 'attributes', 'opt_ins',
        'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'identifiers' => 'array',
        'attributes' => 'array',
        'opt_ins' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function hasOptIn(string $key): bool
    {
        return (bool) data_get($this->opt_ins ?? [], $key);
    }
}
