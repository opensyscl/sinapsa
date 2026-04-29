<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WaTemplate extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $table = 'wa_templates';

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_DISABLED = 'DISABLED';
    public const STATUS_PAUSED = 'PAUSED';

    public const CATEGORY_UTILITY = 'UTILITY';
    public const CATEGORY_MARKETING = 'MARKETING';
    public const CATEGORY_AUTHENTICATION = 'AUTHENTICATION';

    protected $fillable = [
        'workspace_id', 'channel_id',
        'name', 'language', 'category', 'status',
        'components', 'meta_template_id',
        'last_synced_at', 'rejected_reason',
    ];

    protected $casts = [
        'components' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isMarketing(): bool
    {
        return $this->category === self::CATEGORY_MARKETING;
    }
}
