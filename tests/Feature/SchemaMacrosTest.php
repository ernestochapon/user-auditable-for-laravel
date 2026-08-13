<?php

namespace ErnestoChapon\UserAuditable\Tests\Feature;

use ErnestoChapon\UserAuditable\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class SchemaMacrosTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create user table for foreign keys
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        // Clean all tables after each test
        Schema::dropIfExists('test_table_1');
        Schema::dropIfExists('test_table_2');
        Schema::dropIfExists('test_table_3');
        Schema::dropIfExists('test_table_4');
        Schema::dropIfExists('test_table_5');
        Schema::dropIfExists('test_table_6');
        Schema::dropIfExists('test_table_7');
        Schema::dropIfExists('test_table_8');
        Schema::dropIfExists('users_uuid');
        Schema::dropIfExists('users');

        parent::tearDown();
    }

    #[Test]
    public function test_user_auditable_macro_creates_columns(): void
    {
        Schema::create('test_table_1', function (Blueprint $table) {
            $table->id();
            $table->userAuditable();
        });

        $this->assertTrue(Schema::hasColumns('test_table_1', [
            'created_by', 'updated_by', 'deleted_by'
        ]));
    }

    #[Test]
    public function test_full_auditable_macro_creates_all_columns(): void
    {
        Schema::create('test_table_2', function (Blueprint $table) {
            $table->id();
            $table->fullAuditable();
        });

        $this->assertTrue(Schema::hasColumns('test_table_2', [
            'created_at', 'updated_at', 'deleted_at',
            'created_by', 'updated_by', 'deleted_by'
        ]));
    }

    #[Test]
    public function test_user_auditable_with_uuid(): void
    {
        // Create a dedicated users table with UUID primary key for this test
        Schema::create('users_uuid', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('test_table_3', function (Blueprint $table) {
            $table->id();
            $table->userAuditable('users_uuid', 'uuid');
        });

        $columnType = Schema::getColumnType('test_table_3', 'created_by');

        // Type name varies by driver and doctrine/dbal presence
        $this->assertContains($columnType, ['varchar', 'string', 'char']);
    }

    #[Test]
    public function test_event_auditable_creates_both_columns(): void
    {
        Schema::create('test_table_5', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('released');
        });

        $this->assertTrue(Schema::hasColumn('test_table_5', 'released_at'));
        $this->assertTrue(Schema::hasColumn('test_table_5', 'released_by'));
    }

    #[Test]
    public function test_event_auditable_creates_only_at_column(): void
    {
        Schema::create('test_table_6', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('released', 'at');
        });

        $this->assertTrue(Schema::hasColumn('test_table_6', 'released_at'));
        $this->assertFalse(Schema::hasColumn('test_table_6', 'released_by'));
    }

    #[Test]
    public function test_event_auditable_creates_only_by_column(): void
    {
        Schema::create('test_table_7', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('released', 'by');
        });

        $this->assertTrue(Schema::hasColumn('test_table_7', 'released_by'));
        $this->assertFalse(Schema::hasColumn('test_table_7', 'released_at'));
    }

    #[Test]
    public function test_event_auditable_throws_on_empty_event(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name cannot be empty.');

        Schema::create('test_table_5', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('');
        });
    }

    #[Test]
    public function test_event_auditable_throws_on_invalid_specifier(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Invalid column specifier [both]. Must be 'at', 'by', or null.");

        Schema::create('test_table_5', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('released', 'both');
        });
    }
}
