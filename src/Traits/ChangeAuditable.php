<?php

namespace ErnestoChapon\UserAuditable\Traits;

use ErnestoChapon\UserAuditable\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

trait ChangeAuditable
{
    protected array $auditOriginalForUpdate = [];
    protected array $auditDirtyForUpdate = [];
    protected array $auditOriginalForDelete = [];
    protected bool $isRevertingAudit = false;

    public static function bootChangeAuditable(): void
    {
        static::created(function (Model $model) {
            if (!$model->isChangeTrackingEnabled() || !$model->shouldLogEvent('created')) {
                return;
            }

            $model->createAuditLog('created', null, $model->filterAuditValues($model->getAttributes()));
        });

        static::updating(function (Model $model) {
            if (!$model->isChangeTrackingEnabled() || !$model->shouldLogEvent('updated')) {
                return;
            }

            $model->auditOriginalForUpdate = $model->getOriginal();
            $model->auditDirtyForUpdate = $model->getDirty();
        });

        static::updated(function (Model $model) {
            if (!$model->isChangeTrackingEnabled() || !$model->shouldLogEvent('updated')) {
                return;
            }

            $changedKeys = array_keys($model->getChanges());
            if (empty($changedKeys)) {
                $changedKeys = array_keys($model->auditDirtyForUpdate);
            }

            $old = Arr::only($model->auditOriginalForUpdate, $changedKeys);
            $new = Arr::only($model->getAttributes(), $changedKeys);

            $oldFiltered = $model->filterAuditValues($old);
            $newFiltered = $model->filterAuditValues($new);

            if ($oldFiltered === [] && $newFiltered === []) {
                return;
            }

            $model->createAuditLog('updated', $oldFiltered, $newFiltered);
        });

        static::deleting(function (Model $model) {
            if (!$model->isChangeTrackingEnabled() || !$model->shouldLogEvent('deleted')) {
                return;
            }

            $model->auditOriginalForDelete = $model->getOriginal() ?: $model->getAttributes();
        });

        static::deleted(function (Model $model) {
            if (!$model->isChangeTrackingEnabled() || !$model->shouldLogEvent('deleted')) {
                return;
            }

            $old = $model->auditOriginalForDelete ?: ($model->getOriginal() ?: $model->getAttributes());
            $oldFiltered = $model->filterAuditValues($old);

            if ($oldFiltered === []) {
                return;
            }

            $model->createAuditLog('deleted', $oldFiltered, null);
        });

        if (method_exists(static::class, 'restored')) {
            static::restored(function (Model $model) {
                if (!$model->isChangeTrackingEnabled() || !$model->shouldLogEvent('restored')) {
                    return;
                }

                $newFiltered = $model->filterAuditValues($model->getAttributes());
                if ($newFiltered === []) {
                    return;
                }

                $model->createAuditLog('restored', null, $newFiltered);
            });
        }
    }

    public function auditLogs(): MorphMany
    {
        return $this->morphMany(AuditLog::class, 'auditable');
    }

    public function lastAuditLog(): ?AuditLog
    {
        return $this->auditLogs()->latest('id')->first();
    }

    public function revertTo(AuditLog $log): bool
    {
        if ((string) $log->auditable_id !== (string) $this->getKey() || $log->auditable_type !== $this->getMorphClass()) {
            throw new InvalidArgumentException('The provided audit log does not belong to this model instance.');
        }

        $oldValues = is_array($log->old_values) ? $log->old_values : [];
        if ($oldValues === []) {
            throw new InvalidArgumentException('Cannot revert an audit log without old_values.');
        }

        $before = $this->filterAuditValues($this->getAttributes());

        $this->isRevertingAudit = true;
        try {
            $this->forceFill($oldValues);
            $saved = $this->saveQuietly();
        } finally {
            $this->isRevertingAudit = false;
        }

        if (!$saved) {
            return false;
        }

        $after = $this->filterAuditValues($this->getAttributes());
        $this->createAuditLog('reverted', $before, $after);

        return true;
    }

    public function diffBetween(AuditLog $from, AuditLog $to): array
    {
        $fromValues = is_array($from->new_values) ? $from->new_values : [];
        $toValues = is_array($to->new_values) ? $to->new_values : [];

        $changed = [];
        foreach (array_unique(array_merge(array_keys($fromValues), array_keys($toValues))) as $key) {
            $left = $fromValues[$key] ?? null;
            $right = $toValues[$key] ?? null;

            if ($left !== $right) {
                $changed[$key] = [
                    'from' => $left,
                    'to' => $right,
                ];
            }
        }

        return $changed;
    }

    protected function shouldLogEvent(string $event): bool
    {
        if ($this->isRevertingAudit) {
            return false;
        }

        return (bool) config("user-auditable.change_tracking.log_{$event}", true);
    }

    protected function isChangeTrackingEnabled(): bool
    {
        return (bool) config('user-auditable.change_tracking.enabled', true);
    }

    protected function filterAuditValues(array $values): array
    {
        $filtered = $values;

        if (property_exists($this, 'auditInclude') && is_array($this->auditInclude) && $this->auditInclude !== []) {
            $filtered = Arr::only($filtered, $this->auditInclude);
        }

        $hidden = property_exists($this, 'hidden') && is_array($this->hidden) ? $this->hidden : [];
        $exclude = property_exists($this, 'auditExclude') && is_array($this->auditExclude) ? $this->auditExclude : [];

        foreach (array_unique(array_merge($hidden, $exclude)) as $field) {
            unset($filtered[$field]);
        }

        return $filtered;
    }

    protected function createAuditLog(string $event, ?array $oldValues, ?array $newValues): void
    {
        $userIdResolver = config('user-auditable.change_tracking.user_resolver');
        $userTypeResolver = config('user-auditable.change_tracking.user_type_resolver');

        $resolvedUserId = null;
        if (is_callable($userIdResolver)) {
            $resolvedUserId = $userIdResolver($this);
        } elseif (Auth::check()) {
            $resolvedUserId = Auth::id();
        }

        $resolvedUserType = null;
        if (is_callable($userTypeResolver)) {
            $resolvedUserType = $userTypeResolver($this);
        } elseif (Auth::check()) {
            $resolvedUserType = get_class(Auth::user());
        }

        $this->auditLogs()->create([
            'event' => $event,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'user_id' => $resolvedUserId !== null ? (string) $resolvedUserId : null,
            'user_type' => $resolvedUserType,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'tags' => null,
            'created_at' => now(),
        ]);
    }
}
