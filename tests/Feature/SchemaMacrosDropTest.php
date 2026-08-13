<?php

namespace ErnestoChapon\UserAuditable\Tests\Feature;

use ErnestoChapon\UserAuditable\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class SchemaMacrosDropTest extends TestCase
{
    #[Test]
    public function test_drop_user_auditable_removes_audit_columns(): void
    {
        Schema::create('test_drop_user_auditable', function (Blueprint $table) {
            $table->id();
            $table->userAuditable();
        });

        $this->assertTrue(Schema::hasColumn('test_drop_user_auditable', 'created_by'));
        $this->assertTrue(Schema::hasColumn('test_drop_user_auditable', 'updated_by'));
        $this->assertTrue(Schema::hasColumn('test_drop_user_auditable', 'deleted_by'));

        Schema::table('test_drop_user_auditable', function (Blueprint $table) {
            $table->dropUserAuditable();
        });

        $this->assertFalse(Schema::hasColumn('test_drop_user_auditable', 'created_by'));
        $this->assertFalse(Schema::hasColumn('test_drop_user_auditable', 'updated_by'));
        $this->assertFalse(Schema::hasColumn('test_drop_user_auditable', 'deleted_by'));

        Schema::dropIfExists('test_drop_user_auditable');
    }

    #[Test]
    public function test_drop_full_auditable_removes_all_columns(): void
    {
        Schema::create('test_drop_full_auditable', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->fullAuditable();
        });

        $this->assertTrue(Schema::hasColumn('test_drop_full_auditable', 'created_at'));
        $this->assertTrue(Schema::hasColumn('test_drop_full_auditable', 'updated_at'));
        $this->assertTrue(Schema::hasColumn('test_drop_full_auditable', 'deleted_at'));
        $this->assertTrue(Schema::hasColumn('test_drop_full_auditable', 'created_by'));
        $this->assertTrue(Schema::hasColumn('test_drop_full_auditable', 'updated_by'));
        $this->assertTrue(Schema::hasColumn('test_drop_full_auditable', 'deleted_by'));

        Schema::table('test_drop_full_auditable', function (Blueprint $table) {
            $table->dropFullAuditable();
        });

        $this->assertFalse(Schema::hasColumn('test_drop_full_auditable', 'created_at'));
        $this->assertFalse(Schema::hasColumn('test_drop_full_auditable', 'updated_at'));
        $this->assertFalse(Schema::hasColumn('test_drop_full_auditable', 'deleted_at'));
        $this->assertFalse(Schema::hasColumn('test_drop_full_auditable', 'created_by'));
        $this->assertFalse(Schema::hasColumn('test_drop_full_auditable', 'updated_by'));
        $this->assertFalse(Schema::hasColumn('test_drop_full_auditable', 'deleted_by'));

        Schema::dropIfExists('test_drop_full_auditable');
    }

    #[Test]
    public function test_drop_uuid_column_removes_uuid(): void
    {
        Schema::create('test_drop_uuid', function (Blueprint $table) {
            $table->id();
            $table->uuidColumn();
        });

        $this->assertTrue(Schema::hasColumn('test_drop_uuid', 'uuid'));

        Schema::table('test_drop_uuid', function (Blueprint $table) {
            $table->dropUuidColumn();
        });

        $this->assertFalse(Schema::hasColumn('test_drop_uuid', 'uuid'));

        Schema::dropIfExists('test_drop_uuid');
    }

    #[Test]
    public function test_drop_uuid_column_with_custom_name(): void
    {
        Schema::create('test_drop_custom_uuid', function (Blueprint $table) {
            $table->id();
            $table->uuidColumn('product_uuid');
        });

        $this->assertTrue(Schema::hasColumn('test_drop_custom_uuid', 'product_uuid'));

        Schema::table('test_drop_custom_uuid', function (Blueprint $table) {
            $table->dropUuidColumn('product_uuid');
        });

        $this->assertFalse(Schema::hasColumn('test_drop_custom_uuid', 'product_uuid'));

        Schema::dropIfExists('test_drop_custom_uuid');
    }

    #[Test]
    public function test_drop_ulid_column_removes_ulid(): void
    {
        Schema::create('test_drop_ulid', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn();
        });

        $this->assertTrue(Schema::hasColumn('test_drop_ulid', 'ulid'));

        Schema::table('test_drop_ulid', function (Blueprint $table) {
            $table->dropUlidColumn();
        });

        $this->assertFalse(Schema::hasColumn('test_drop_ulid', 'ulid'));

        Schema::dropIfExists('test_drop_ulid');
    }

    #[Test]
    public function test_drop_ulid_column_with_custom_name(): void
    {
        Schema::create('test_drop_custom_ulid', function (Blueprint $table) {
            $table->id();
            $table->ulidColumn('order_ulid');
        });

        $this->assertTrue(Schema::hasColumn('test_drop_custom_ulid', 'order_ulid'));

        Schema::table('test_drop_custom_ulid', function (Blueprint $table) {
            $table->dropUlidColumn('order_ulid');
        });

        $this->assertFalse(Schema::hasColumn('test_drop_custom_ulid', 'order_ulid'));

        Schema::dropIfExists('test_drop_custom_ulid');
    }

    #[Test]
    public function test_drop_status_column_removes_status(): void
    {
        Schema::create('test_drop_status', function (Blueprint $table) {
            $table->id();
            $table->statusColumn();
        });

        $this->assertTrue(Schema::hasColumn('test_drop_status', 'status'));

        Schema::table('test_drop_status', function (Blueprint $table) {
            $table->dropStatusColumn();
        });

        $this->assertFalse(Schema::hasColumn('test_drop_status', 'status'));

        Schema::dropIfExists('test_drop_status');
    }

    #[Test]
    public function test_drop_status_column_with_custom_name(): void
    {
        Schema::create('test_drop_custom_status', function (Blueprint $table) {
            $table->id();
            $table->statusColumn('user_status', ['active', 'inactive'], 'active');
        });

        $this->assertTrue(Schema::hasColumn('test_drop_custom_status', 'user_status'));

        Schema::table('test_drop_custom_status', function (Blueprint $table) {
            $table->dropStatusColumn('user_status');
        });

        $this->assertFalse(Schema::hasColumn('test_drop_custom_status', 'user_status'));

        Schema::dropIfExists('test_drop_custom_status');
    }

    #[Test]
    public function test_drop_event_auditable_removes_both_columns(): void
    {
        Schema::create('test_drop_event', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('released');
        });

        $this->assertTrue(Schema::hasColumn('test_drop_event', 'released_by'));
        $this->assertTrue(Schema::hasColumn('test_drop_event', 'released_at'));

        Schema::table('test_drop_event', function (Blueprint $table) {
            $table->dropEventAuditable('released');
        });

        $this->assertFalse(Schema::hasColumn('test_drop_event', 'released_by'));
        $this->assertFalse(Schema::hasColumn('test_drop_event', 'released_at'));

        Schema::dropIfExists('test_drop_event');
    }

    #[Test]
    public function test_drop_event_auditable_with_by_only(): void
    {
        Schema::create('test_drop_event_by', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('approved', 'by');
        });

        $this->assertTrue(Schema::hasColumn('test_drop_event_by', 'approved_by'));
        $this->assertFalse(Schema::hasColumn('test_drop_event_by', 'approved_at'));

        Schema::table('test_drop_event_by', function (Blueprint $table) {
            $table->dropEventAuditable('approved', 'by');
        });

        $this->assertFalse(Schema::hasColumn('test_drop_event_by', 'approved_by'));

        Schema::dropIfExists('test_drop_event_by');
    }

    #[Test]
    public function test_drop_event_auditable_with_at_only(): void
    {
        Schema::create('test_drop_event_at', function (Blueprint $table) {
            $table->id();
            $table->eventAuditable('archived', 'at');
        });

        $this->assertTrue(Schema::hasColumn('test_drop_event_at', 'archived_at'));
        $this->assertFalse(Schema::hasColumn('test_drop_event_at', 'archived_by'));

        Schema::table('test_drop_event_at', function (Blueprint $table) {
            $table->dropEventAuditable('archived', 'at');
        });

        $this->assertFalse(Schema::hasColumn('test_drop_event_at', 'archived_at'));

        Schema::dropIfExists('test_drop_event_at');
    }
}
