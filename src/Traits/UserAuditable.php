<?php

namespace ErnestoChapon\UserAuditable\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

trait UserAuditable
{
    /**
     * Cache for database column existence checks
     */
    protected array $auditableColumnCache = [];

    /**
     * Boot the UserAuditable trait
     */
    public static function bootUserAuditable(): void
    {
        static::creating(function (Model $model) {
            if (Auth::check() && static::hasAuditableColumn($model, 'created_by')) {
                $model->created_by = Auth::id();
            }
        });

        static::updating(function (Model $model) {
            if (Auth::check() && static::hasAuditableColumn($model, 'updated_by')) {
                $model->updated_by = Auth::id();
            }
        });

        static::deleting(function (Model $model) {
            if (
                Auth::check()
                && in_array(SoftDeletes::class, class_uses_recursive($model))
                && !$model->isForceDeleting()
                && static::hasAuditableColumn($model, 'deleted_by')
            ) {
                // Direct DB update to avoid triggering Eloquent updating event
                $model->getConnection()
                      ->table($model->getTable())
                      ->where($model->getKeyName(), $model->getKey())
                      ->update(['deleted_by' => Auth::id()]);

                $model->deleted_by = Auth::id();
            }
        });

        // Only record restoring if the model uses SoftDeletes
        if (method_exists(static::class, 'restoring')) {
            static::restoring(function (Model $model) {
                if (Auth::check() && static::hasAuditableColumn($model, 'deleted_by')) {
                    $model->deleted_by = null;
                }
            });
        }
    }

    /**
     * Check if a specific column exists in the model's table and cache the result
     */
    protected static function hasAuditableColumn(Model $model, string $column): bool
    {
        $table = $model->getTable();
        $key = "{$table}.{$column}";

        if (!isset($model->auditableColumnCache[$key])) {
            $model->auditableColumnCache[$key] = Schema::hasColumn($table, $column);
        }

        return $model->auditableColumnCache[$key];
    }

    /**
     * Get the user who created the record
     */
    public function creator(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        return $this->belongsTo($userModel, 'created_by');
    }

    /**
     * Get the user who updated the record
     */
    public function updater(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        return $this->belongsTo($userModel, 'updated_by');
    }

    /**
     * Get the user who deleted the record
     */
    public function deleter(): BelongsTo
    {
        $userModel = config('auth.providers.users.model', 'App\\Models\\User');
        return $this->belongsTo($userModel, 'deleted_by');
    }

    /**
     * Scope a query to only include records created by a specific user
     */
    public function scopeCreatedBy(Builder $query, int|string $userId): Builder
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope a query to only include records updated by a specific user
     */
    public function scopeUpdatedBy(Builder $query, int|string $userId): Builder
    {
        return $query->where('updated_by', $userId);
    }

    /**
     * Scope a query to only include records deleted by a specific user
     */
    public function scopeDeletedBy(Builder $query, int|string $userId): Builder
    {
        return $query->where('deleted_by', $userId);
    }
}
