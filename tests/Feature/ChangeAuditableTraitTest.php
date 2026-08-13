<?php

namespace ErnestoChapon\UserAuditable\Tests\Feature;

use ErnestoChapon\UserAuditable\Models\AuditLog;
use ErnestoChapon\UserAuditable\Tests\TestCase;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestModelWithChangeAuditable;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestModelWithChangeAuditableInclude;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;

class ChangeAuditableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('test_models_with_change_auditable', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->nullable();
            $table->string('secret')->nullable();
            $table->eventAuditable('released', 'by');
            $table->userAuditable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_models_with_change_auditable_include', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->auditLogTable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('test_models_with_change_auditable_include');
        Schema::dropIfExists('test_models_with_change_auditable');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function test_created_event_logs_new_values_only(): void
    {
        $model = TestModelWithChangeAuditable::create([
            'name' => 'Original',
            'status' => 'draft',
            'secret' => 'dont-log',
        ]);

        $log = AuditLog::query()->where('event', 'created')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->old_values);
        $this->assertEquals('Original', $log->new_values['name']);
        $this->assertArrayNotHasKey('secret', $log->new_values);
        $this->assertArrayNotHasKey('status', $log->new_values);
        $this->assertEquals($model->getMorphClass(), $log->auditable_type);
        $this->assertEquals((string) $model->id, (string) $log->auditable_id);
    }

    #[Test]
    public function test_updated_event_logs_old_and_new_values(): void
    {
        $model = TestModelWithChangeAuditable::create([
            'name' => 'Before',
            'status' => 'draft',
        ]);

        $model->update([
            'name' => 'After',
            'status' => 'published',
            'secret' => 'hidden',
        ]);

        $log = AuditLog::query()->where('event', 'updated')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertEquals('Before', $log->old_values['name']);
        $this->assertEquals('After', $log->new_values['name']);
        $this->assertArrayNotHasKey('secret', $log->new_values);
        $this->assertArrayNotHasKey('status', $log->new_values);
    }

    #[Test]
    public function test_deleted_event_logs_old_values_only(): void
    {
        $model = TestModelWithChangeAuditable::create([
            'name' => 'To delete',
        ]);

        $model->delete();

        $log = AuditLog::query()->where('event', 'deleted')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertEquals('To delete', $log->old_values['name']);
        $this->assertNull($log->new_values);
    }

    #[Test]
    public function test_restored_event_logs_new_values_only(): void
    {
        $model = TestModelWithChangeAuditable::create([
            'name' => 'To restore',
        ]);

        $model->delete();
        $model->restore();

        $log = AuditLog::query()->where('event', 'restored')->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->old_values);
        $this->assertEquals('To restore', $log->new_values['name']);
    }

    #[Test]
    public function test_audit_include_property_logs_only_listed_fields(): void
    {
        $model = TestModelWithChangeAuditableInclude::create([
            'name' => 'Include this',
            'status' => 'Ignore this',
        ]);

        $log = $model->lastAuditLog();

        $this->assertNotNull($log);
        $this->assertEquals(['name' => 'Include this'], $log->new_values);
    }

    #[Test]
    public function test_last_audit_log_and_diff_between(): void
    {
        $model = TestModelWithChangeAuditable::create([
            'name' => 'v1',
        ]);

        $first = $model->lastAuditLog();

        $model->update(['name' => 'v2']);
        $second = $model->lastAuditLog();

        $this->assertNotNull($second);
        $diff = $model->diffBetween($first, $second);

        $this->assertArrayHasKey('name', $diff);
        $this->assertEquals('v1', $diff['name']['from']);
        $this->assertEquals('v2', $diff['name']['to']);
    }

    #[Test]
    public function test_revert_to_restores_model_and_logs_reverted_event(): void
    {
        $model = TestModelWithChangeAuditable::create(['name' => 'Before']);
        $model->update(['name' => 'After']);

        $updatedLog = AuditLog::query()->where('event', 'updated')->latest('id')->first();
        $result = $model->revertTo($updatedLog);

        $this->assertTrue($result);
        $this->assertEquals('Before', $model->fresh()->name);

        $revertedLog = AuditLog::query()->where('event', 'reverted')->latest('id')->first();
        $this->assertNotNull($revertedLog);
        $this->assertEquals('After', $revertedLog->old_values['name']);
        $this->assertEquals('Before', $revertedLog->new_values['name']);
    }

    #[Test]
    public function test_revert_to_throws_when_log_does_not_belong_to_model(): void
    {
        $first = TestModelWithChangeAuditable::create(['name' => 'First']);
        $second = TestModelWithChangeAuditable::create(['name' => 'Second']);

        $foreignLog = $first->lastAuditLog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not belong to this model');

        $second->revertTo($foreignLog);
    }

    #[Test]
    public function test_revert_to_throws_when_old_values_is_empty(): void
    {
        $model = TestModelWithChangeAuditable::create(['name' => 'No old']);
        $createdLog = $model->lastAuditLog();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('without old_values');

        $model->revertTo($createdLog);
    }

    #[Test]
    public function test_works_with_user_auditable_and_event_auditable_traits(): void
    {
        $user = TestUser::create([
            'name' => 'John',
            'email' => 'john@example.com',
            'password' => Hash::make('password'),
        ]);

        Auth::login($user);

        $model = TestModelWithChangeAuditable::create([
            'name' => 'Combined',
            'released_by' => $user->id,
        ]);

        $this->assertEquals($user->id, $model->created_by);
        $this->assertEquals($user->id, $model->releasedBy()->first()->id);

        $log = $model->lastAuditLog();
        $this->assertNotNull($log);
        $this->assertEquals((string) $user->id, (string) $log->user_id);
    }
}
