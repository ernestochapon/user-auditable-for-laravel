<?php

namespace ErnestoChapon\UserAuditable\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class UserAuditableServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishConfig();
            $this->publishMigrations();
        }

        $this->registerMacros();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/user-auditable.php',
            'user-auditable'
        );
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            __DIR__.'/../../config/user-auditable.php' => config_path('user-auditable.php'),
        ], 'user-auditable-config');
    }

    protected function publishMigrations(): void
    {
        $this->publishes([
            __DIR__.'/../../database/migrations' => database_path('migrations'),
        ], 'user-auditable-migrations');
    }

    protected function registerMacros(): void
    {
        $enabledMacros = config('user-auditable.enabled_macros', []);

        foreach ($enabledMacros as $macro) {
            $methodName = Str::camel($macro);
            if (method_exists($this, $methodName)) {
                $this->{$methodName}();
            }
        }
    }

    protected function userAuditable(): void
    {
        Blueprint::macro('userAuditable', function (
            ?string $userTable = null,
            ?string $keyType = null
        ) {
            /** @var Blueprint $this */

            $userTable = $userTable ?? config('user-auditable.defaults.user_table', 'users');
            $keyType   = $keyType   ?? config('user-auditable.defaults.key_type', 'id');

            $validKeyTypes = ['id', 'uuid', 'ulid'];
            if (!in_array($keyType, $validKeyTypes)) {
                throw new InvalidArgumentException(
                    "Invalid key type: {$keyType}. Must be one of: " . implode(', ', $validKeyTypes)
                );
            }

            if (empty($userTable)) {
                throw new InvalidArgumentException('User table name cannot be empty');
            }

            switch ($keyType) {
                case 'uuid':
                    $this->foreignUuid('created_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    $this->foreignUuid('updated_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    $this->foreignUuid('deleted_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    break;

                case 'ulid':
                    $this->foreignUlid('created_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    $this->foreignUlid('updated_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    $this->foreignUlid('deleted_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    break;

                default: // id
                    $this->foreignId('created_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    $this->foreignId('updated_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
                    $this->foreignId('deleted_by')
                         ->nullable()
                         ->index()
                         ->constrained($userTable)
                         ->onDelete('set null');
            }

            return $this;
        });
    }

    protected function dropUserAuditable(): void
    {
        Blueprint::macro('dropUserAuditable', function (bool $dropForeign = true) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();

            // Use separate Schema::table() calls so each operation is independent
            // Blueprint defers commands, so try-catch inside a single Blueprint won't catch errors
            foreach (['created_by', 'updated_by', 'deleted_by'] as $col) {
                if ($dropForeign) {
                    try {
                        Schema::table($tableName, function (Blueprint $t) use ($col) {
                            $t->dropForeign([$col]);
                        });
                    } catch (Throwable $e) {
                        // SQLite doesn't support dropping foreign keys
                    }
                }

                try {
                    Schema::table($tableName, function (Blueprint $t) use ($col) {
                        $t->dropIndex([$col]);
                    });
                } catch (Throwable $e) {
                    // Index may not exist
                }

                try {
                    Schema::table($tableName, function (Blueprint $t) use ($col) {
                        $t->dropColumn($col);
                    });
                } catch (Throwable $e) {
                    // Column may not exist
                }
            }

            return $this;
        });
    }

    protected function uuidColumn(): void
    {
        Blueprint::macro('uuidColumn', function (string $columnName = 'uuid') {
            /** @var Blueprint $this */
            $this->uuid($columnName)->unique();
            return $this;
        });
    }

    protected function ulidColumn(): void
    {
        Blueprint::macro('ulidColumn', function (string $columnName = 'ulid') {
            /** @var Blueprint $this */
            $this->ulid($columnName)->unique();
            return $this;
        });
    }

    protected function statusColumn(): void
    {
        Blueprint::macro('statusColumn', function (
            string $columnName = 'status',
            array $allowed = ['active', 'inactive', 'pending'],
            string $default = 'active'
        ) {
            /** @var Blueprint $this */
            $this->enum($columnName, $allowed)->default($default);
            return $this;
        });
    }

    protected function fullAuditable(): void
    {
        if (!in_array('user_auditable', config('user-auditable.enabled_macros', []))) {
            throw new RuntimeException(
                'The [full_auditable] macro requires [user_auditable] to be enabled in config/user-auditable.php.'
            );
        }

        Blueprint::macro('fullAuditable', function (
            ?string $userTable = null,
            ?string $keyType = null
        ) {
            /** @var Blueprint $this */
            $userTable = $userTable ?? config('user-auditable.defaults.user_table', 'users');
            $keyType   = $keyType   ?? config('user-auditable.defaults.key_type', 'id');

            $this->timestamps();
            $this->softDeletes();
            $this->userAuditable($userTable, $keyType);
            return $this;
        });
    }

    protected function eventAuditable(): void
    {
        Blueprint::macro('eventAuditable', function (string $event, ?string $column = null) {
            /** @var Blueprint $this */
            if (empty($event)) {
                throw new InvalidArgumentException('Event name cannot be empty.');
            }
            if ($column !== null && !in_array($column, ['at', 'by'])) {
                throw new InvalidArgumentException(
                    "Invalid column specifier [{$column}]. Must be 'at', 'by', or null."
                );
            }

            if ($column === null || $column === 'at') {
                $this->timestamp("{$event}_at")->nullable();
            }

            if ($column === null || $column === 'by') {
                $userTable = config('user-auditable.defaults.user_table', 'users');
                $keyType   = config('user-auditable.defaults.key_type', 'id');

                switch ($keyType) {
                    case 'uuid':
                        $this->foreignUuid("{$event}_by")->nullable()->index()
                             ->constrained($userTable)->onDelete('set null');
                        break;
                    case 'ulid':
                        $this->foreignUlid("{$event}_by")->nullable()->index()
                             ->constrained($userTable)->onDelete('set null');
                        break;
                    default:
                        $this->foreignId("{$event}_by")->nullable()->index()
                             ->constrained($userTable)->onDelete('set null');
                }
            }

            return $this;
        });
    }

    protected function dropEventAuditable(): void
    {
        Blueprint::macro('dropEventAuditable', function (
            string $event,
            ?string $column = null,
            bool $dropForeign = true
        ) {
            /** @var Blueprint $this */
            if (empty($event)) {
                throw new InvalidArgumentException('Event name cannot be empty.');
            }
            if ($column !== null && !in_array($column, ['at', 'by'])) {
                throw new InvalidArgumentException(
                    "Invalid column specifier [{$column}]. Must be 'at', 'by', or null."
                );
            }

            $tableName = $this->getTable();

            // Drop _by column (foreign key + index + column) using separate calls
            if ($column === null || $column === 'by') {
                if ($dropForeign) {
                    try {
                        Schema::table($tableName, function (Blueprint $t) use ($event) {
                            $t->dropForeign(["{$event}_by"]);
                        });
                    } catch (Throwable $e) {
                        // SQLite doesn't support dropping foreign keys
                    }
                }

                try {
                    Schema::table($tableName, function (Blueprint $t) use ($event) {
                        $t->dropIndex(["{$event}_by"]);
                    });
                } catch (Throwable $e) {
                    // Index may not exist
                }

                try {
                    Schema::table($tableName, function (Blueprint $t) use ($event) {
                        $t->dropColumn("{$event}_by");
                    });
                } catch (Throwable $e) {
                    // Column may not exist
                }
            }

            // Drop _at column (simple timestamp, no index)
            if ($column === null || $column === 'at') {
                try {
                    Schema::table($tableName, function (Blueprint $t) use ($event) {
                        $t->dropColumn("{$event}_at");
                    });
                } catch (Throwable $e) {
                    // Column may not exist
                }
            }

            return $this;
        });
    }

    protected function dropFullAuditable(): void
    {
        Blueprint::macro('dropFullAuditable', function (bool $dropForeign = true) {
            /** @var Blueprint $this */
            $tableName = $this->getTable();

            // Drop timestamps in a separate call
            try {
                Schema::table($tableName, function (Blueprint $t) {
                    $t->dropTimestamps();
                });
            } catch (Throwable $e) {
                // Columns may not exist
            }

            // Drop soft deletes in a separate call
            try {
                Schema::table($tableName, function (Blueprint $t) {
                    $t->dropSoftDeletes();
                });
            } catch (Throwable $e) {
                // Column may not exist
            }

            // Drop user auditable columns (uses separate Schema::table calls internally)
            $this->dropUserAuditable($dropForeign);

            return $this;
        });
    }

    protected function dropUuidColumn(): void
    {
        Blueprint::macro('dropUuidColumn', function (string $columnName = 'uuid') {
            /** @var Blueprint $this */
            $tableName = $this->getTable();

            try {
                Schema::table($tableName, function (Blueprint $t) use ($columnName) {
                    $t->dropUnique([$columnName]);
                });
            } catch (Throwable $e) {
                // Unique index may not exist or have a different name
            }

            try {
                Schema::table($tableName, function (Blueprint $t) use ($columnName) {
                    $t->dropColumn($columnName);
                });
            } catch (Throwable $e) {
                // Column may not exist
            }

            return $this;
        });
    }

    protected function dropUlidColumn(): void
    {
        Blueprint::macro('dropUlidColumn', function (string $columnName = 'ulid') {
            /** @var Blueprint $this */
            $tableName = $this->getTable();

            try {
                Schema::table($tableName, function (Blueprint $t) use ($columnName) {
                    $t->dropUnique([$columnName]);
                });
            } catch (Throwable $e) {
                // Unique index may not exist or have a different name
            }

            try {
                Schema::table($tableName, function (Blueprint $t) use ($columnName) {
                    $t->dropColumn($columnName);
                });
            } catch (Throwable $e) {
                // Column may not exist
            }

            return $this;
        });
    }

    protected function dropStatusColumn(): void
    {
        Blueprint::macro('dropStatusColumn', function (string $columnName = 'status') {
            /** @var Blueprint $this */
            $this->dropColumn($columnName);
            return $this;
        });
    }

    protected function auditLogTable(): void
    {
        Blueprint::macro('auditLogTable', function () {
            /** @var Blueprint $this */
            $this->id();
            $this->string('auditable_type');
            $this->string('auditable_id');
            $this->string('event');
            $this->json('old_values')->nullable();
            $this->json('new_values')->nullable();
            $this->string('user_id')->nullable();
            $this->string('user_type')->nullable();
            $this->string('ip_address', 45)->nullable();
            $this->text('user_agent')->nullable();
            $this->json('tags')->nullable();
            $this->timestamp('created_at')->useCurrent();

            $this->index(['auditable_type', 'auditable_id']);
            $this->index('event');
            $this->index('user_id');
            $this->index('created_at');

            return $this;
        });
    }

    protected function dropAuditLogTable(): void
    {
        Blueprint::macro('dropAuditLogTable', function () {
            /** @var Blueprint $this */
            $tableName = $this->getTable();
            Schema::dropIfExists($tableName);
            return $this;
        });
    }
}
