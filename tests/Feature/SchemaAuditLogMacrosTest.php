<?php

namespace ErnestoChapon\UserAuditable\Tests\Feature;

use ErnestoChapon\UserAuditable\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class SchemaAuditLogMacrosTest extends TestCase
{
    #[Test]
    public function test_audit_log_table_macro_creates_expected_columns(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->auditLogTable();
        });

        $this->assertTrue(Schema::hasColumns('audit_logs', [
            'id',
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
        ]));

        $this->assertFalse(Schema::hasColumn('audit_logs', 'updated_at'));

        Schema::dropIfExists('audit_logs');
    }

    #[Test]
    public function test_drop_audit_log_table_macro_drops_table(): void
    {
        Schema::create('audit_logs_to_drop', function (Blueprint $table) {
            $table->auditLogTable();
        });

        $this->assertTrue(Schema::hasTable('audit_logs_to_drop'));

        Schema::table('audit_logs_to_drop', function (Blueprint $table) {
            $table->dropAuditLogTable();
        });

        $this->assertFalse(Schema::hasTable('audit_logs_to_drop'));
    }
}
