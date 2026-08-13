<?php

namespace ErnestoChapon\UserAuditable\Tests\Feature;

use ErnestoChapon\UserAuditable\Tests\TestCase;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestModelWithEventAuditable;
use ErnestoChapon\UserAuditable\Tests\TestModels\TestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class EventAuditableTraitTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create the users table
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        // Create test table with event auditable columns
        Schema::create('test_models_with_event_auditable', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->eventAuditable('released');  // Creates released_by + released_at
            $table->eventAuditable('approved', 'by'); // Creates only approved_by
            $table->eventAuditable('archived', 'at'); // Creates only archived_at
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('test_models_with_event_auditable');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function test_dynamic_event_by_method_returns_belongs_to_relationship(): void
    {
        $user = TestUser::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'password' => Hash::make('password')
        ]);

        $model = TestModelWithEventAuditable::create([
            'name' => 'Test Product',
            'released_by' => $user->id,
        ]);

        $relationship = $model->releasedBy();

        $this->assertNotNull($relationship);
        $this->assertTrue(method_exists($relationship, 'get'));
        $this->assertEquals($user->id, $relationship->first()->id);
    }

    #[Test]
    public function test_dynamic_event_at_method_returns_timestamp(): void
    {
        $model = TestModelWithEventAuditable::create([
            'name' => 'Test Product',
            'released_at' => now(),
        ]);

        $timestamp = $model->releasedAt();

        $this->assertNotNull($timestamp);
        $this->assertEquals($model->released_at->timestamp, $timestamp->timestamp);
    }

    #[Test]
    public function test_multiple_event_methods_work_together(): void
    {
        $user1 = TestUser::create([
            'name' => 'Releaser',
            'email' => 'releaser@example.com',
            'password' => Hash::make('password')
        ]);

        $user2 = TestUser::create([
            'name' => 'Approver',
            'email' => 'approver@example.com',
            'password' => Hash::make('password')
        ]);

        $model = TestModelWithEventAuditable::create([
            'name' => 'Test Product',
            'released_by' => $user1->id,
            'released_at' => now(),
            'approved_by' => $user2->id,
        ]);

        $this->assertEquals($user1->id, $model->releasedBy()->first()->id);
        $this->assertEquals($user2->id, $model->approvedBy()->first()->id);
        $this->assertNotNull($model->releasedAt());
    }

    #[Test]
    public function test_dynamic_query_scope_filters_by_event_user(): void
    {
        $user1 = TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password')
        ]);

        $user2 = TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password')
        ]);

        TestModelWithEventAuditable::create([
            'name' => 'Product 1',
            'released_by' => $user1->id,
        ]);

        TestModelWithEventAuditable::create([
            'name' => 'Product 2',
            'released_by' => $user2->id,
        ]);

        $filtered = TestModelWithEventAuditable::releasedBy($user1->id)->get();

        $this->assertEquals(1, $filtered->count());
        $this->assertEquals('Product 1', $filtered->first()->name);
    }

    #[Test]
    public function test_dynamic_query_scope_with_different_events(): void
    {
        $user1 = TestUser::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password')
        ]);

        $user2 = TestUser::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password')
        ]);

        TestModelWithEventAuditable::create([
            'name' => 'Product 1',
            'released_by' => $user1->id,
            'approved_by' => $user2->id,
        ]);

        $releaseFiltered = TestModelWithEventAuditable::releasedBy($user1->id)->get();
        $approveFiltered = TestModelWithEventAuditable::approvedBy($user2->id)->get();

        $this->assertEquals(1, $releaseFiltered->count());
        $this->assertEquals(1, $approveFiltered->count());
        $this->assertEquals('Product 1', $releaseFiltered->first()->name);
    }

    #[Test]
    public function test_event_method_returns_null_for_nonexistent_column(): void
    {
        $model = TestModelWithEventAuditable::create([
            'name' => 'Test Product',
        ]);

        // This column doesn't exist
        $result = $model->nonexistentBy();

        $this->assertNull($result);
    }

    #[Test]
    public function test_event_timestamp_returns_null_for_nonexistent_column(): void
    {
        $model = TestModelWithEventAuditable::create([
            'name' => 'Test Product',
        ]);

        // This column doesn't exist
        $result = $model->nonexistentAt();

        $this->assertNull($result);
    }

    #[Test]
    public function test_event_query_scope_handles_nonexistent_column_gracefully(): void
    {
        TestModelWithEventAuditable::create([
            'name' => 'Product 1',
        ]);

        // Should not throw error, just return the query builder
        $filtered = TestModelWithEventAuditable::nonexistentBy(1)->get();

        // Should return all records (no WHERE clause applied)
        $this->assertEquals(1, $filtered->count());
    }

    #[Test]
    public function test_column_cache_improves_performance(): void
    {
        $model = TestModelWithEventAuditable::create([
            'name' => 'Test Product',
            'released_by' => 1,
        ]);

        // First call checks the database
        $model->releasedBy();

        // Second call should use cache
        $model->releasedBy();

        // Verify cache is populated
        $reflection = new \ReflectionClass($model);
        $property = $reflection->getProperty('auditableColumnCache');
        $property->setAccessible(true);
        $cache = $property->getValue($model);

        $this->assertArrayHasKey('released_by', $cache);
        $this->assertTrue($cache['released_by']);
    }

    #[Test]
    public function test_undefined_method_throws_exception(): void
    {
        $this->expectException(\BadMethodCallException::class);

        $model = TestModelWithEventAuditable::create([
            'name' => 'Test Product',
        ]);

        // This should throw exception (doesn't match eventBy or eventAt pattern)
        $model->invalidMethod();
    }

    #[Test]
    public function test_undefined_static_method_throws_exception(): void
    {
        $this->expectException(\BadMethodCallException::class);

        // This should throw exception (doesn't match eventBy pattern)
        TestModelWithEventAuditable::invalidStaticMethod(1);
    }

    #[Test]
    public function test_static_method_without_user_id_throws_exception(): void
    {
        $this->expectException(\BadMethodCallException::class);

        // This should throw exception (missing required userId argument)
        TestModelWithEventAuditable::releasedBy();
    }
}
