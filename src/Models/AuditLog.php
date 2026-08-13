<?php

namespace ErnestoChapon\UserAuditable\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'event',
        'old_values',
        'new_values',
        'user_id',
        'user_type',
        'ip_address',
        'user_agent',
        'tags',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'tags' => 'array',
        'created_at' => 'datetime',
    ];

    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable((string) config('user-auditable.change_tracking.table', 'audit_logs'));
    }

    public function auditable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        $userModel = $this->user_type ?: config('auth.providers.users.model', 'App\\Models\\User');

        return $this->belongsTo($userModel, 'user_id');
    }

    public static function pruneOlderThan(int $days): int
    {
        return static::query()
            ->where('created_at', '<', now()->subDays($days))
            ->delete();
    }
}
