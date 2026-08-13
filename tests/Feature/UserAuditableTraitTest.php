<?php

namespace ErnestoChapon\UserAuditable\Tests\Feature;

use ErnestoChapon\UserAuditable\Tests\TestCase;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestModelWithoutSoftDeletes;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestModelWithSoftDeletes;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestUser;
use ErnestoChapon\UserAuditable\Traits\UserAuditable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class UserAuditableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create the users table first (with the exact name the macro expects)
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Create test tables - explicitly reference the 'users' table
        Schema::create('test_models_with_soft_deletes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->userAuditable('users'); // Explicitly reference 'users' table
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('test_models_without_soft_deletes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->userAuditable('users'); // Explicitly reference 'users' table
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        // Clean tables in reverse order (respect foreign key constraints)
        Schema::dropIfExists('test_models_with_soft_deletes');
        Schema::dropIfExists('test_models_without_soft_deletes');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function test_automatically_sets_created_by(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);

        Auth::login($user);

        $model = TestModelWithSoftDeletes::create(['name' => 'Test Model']);

        $this->assertEquals($user->id, $model->created_by);
        $this->assertNull($model->updated_by);
    }

    #[Test]
    public function test_automatically_sets_updated_by(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);

        Auth::login($user);

        $model = TestModelWithSoftDeletes::create(['name' => 'Test Model']);
        $model->update(['name' => 'Updated Model']);

        $this->assertEquals($user->id, $model->updated_by);
    }

    #[Test]
    public function test_relationships_exist(): void
    {
        $model = new TestModelWithSoftDeletes();

        $this->assertTrue(method_exists($model, 'creator'));
        $this->assertTrue(method_exists($model, 'updater'));
        $this->assertTrue(method_exists($model, 'deleter'));
    }

    #[Test]
    public function test_works_without_soft_deletes(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);

        Auth::login($user);

        $model = TestModelWithoutSoftDeletes::create(['name' => 'Test Model']);
        $model->update(['name' => 'Updated Model']);

        $this->assertEquals($user->id, $model->created_by);
        $this->assertEquals($user->id, $model->updated_by);

        // It shouldn't fail even if you don't have SoftDeletes
        $model->delete();
        $this->assertTrue(true);
    }

    #[Test]
    public function test_deleted_by_is_set_on_soft_delete(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password')
        ]);

        Auth::login($user);

        $model = TestModelWithSoftDeletes::create(['name' => 'Test Model']);
        $model->delete();

        $this->assertEquals($user->id, $model->deleted_by);
        $this->assertNotNull($model->deleted_at);
    }

    #[Test]
    public function test_restoring_clears_deleted_by(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        Auth::login($user);

        $model = TestModelWithSoftDeletes::create(['name' => 'Test Model']);
        $model->delete();

        $this->assertEquals($user->id, $model->deleted_by);

        $model->restore();

        $this->assertNull($model->fresh()->deleted_by);
        $this->assertNull($model->fresh()->deleted_at);
    }

    #[Test]
    public function test_scope_created_by_filters_records(): void
    {
        $user1 = TestUser::create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
        ]);

        $user2 = TestUser::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
        ]);

        Auth::login($user1);
        TestModelWithSoftDeletes::create(['name' => 'Model by User 1']);

        Auth::login($user2);
        TestModelWithSoftDeletes::create(['name' => 'Model by User 2']);

        $results = TestModelWithSoftDeletes::createdBy($user1->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Model by User 1', $results->first()->name);
    }

    #[Test]
    public function test_scope_updated_by_filters_records(): void
    {
        $user1 = TestUser::create([
            'name' => 'User One',
            'email' => 'user1@example.com',
            'password' => Hash::make('password'),
        ]);

        $user2 = TestUser::create([
            'name' => 'User Two',
            'email' => 'user2@example.com',
            'password' => Hash::make('password'),
        ]);

        Auth::login($user1);
        $model = TestModelWithSoftDeletes::create(['name' => 'Original Model']);

        Auth::login($user2);
        $model->update(['name' => 'Updated Model']);

        $results = TestModelWithSoftDeletes::updatedBy($user2->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('Updated Model', $results->first()->name);
    }

    #[Test]
    public function test_scope_deleted_by_filters_records(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        Auth::login($user);

        $model = TestModelWithSoftDeletes::create(['name' => 'To Be Deleted']);
        TestModelWithSoftDeletes::create(['name' => 'Not Deleted']);

        $model->delete();

        $results = TestModelWithSoftDeletes::withTrashed()->deletedBy($user->id)->get();

        $this->assertCount(1, $results);
        $this->assertEquals('To Be Deleted', $results->first()->name);
    }

    #[Test]
    public function test_relationships_return_correct_user_instances(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        Auth::login($user);

        $model = TestModelWithSoftDeletes::create(['name' => 'Test Model']);
        $this->assertEquals($user->id, $model->creator->id);

        $model->update(['name' => 'Updated Model']);
        $this->assertEquals($user->id, $model->fresh()->updater->id);

        $model->delete();
        $this->assertEquals($user->id, $model->deleter->id);
    }

    #[Test]
    public function test_force_delete_does_not_set_deleted_by(): void
    {
        $user = TestUser::create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password'),
        ]);

        Auth::login($user);

        $model = TestModelWithSoftDeletes::create(['name' => 'To Force Delete']);
        $modelId = $model->id;

        $model->forceDelete();

        $this->assertNull(TestModelWithSoftDeletes::withTrashed()->find($modelId));
    }
}
