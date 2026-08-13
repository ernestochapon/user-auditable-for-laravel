<?php

namespace ErnestoChapon\UserAuditable\Tests\Unit;

use ErnestoChapon\UserAuditable\Models\AuditLog;
use ErnestoChapon\UserAuditable\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;

class AuditLogModelTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->auditLogTable();
        });
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('audit_logs');

        parent::tearDown();
    }

    #[Test]
    public function test_prune_older_than_deletes_old_logs(): void
    {
        AuditLog::query()->create([
            'auditable_type' => 'Test',
            'auditable_id' => '1',
            'event' => 'updated',
            'old_values' => ['name' => 'old'],
            'new_values' => ['name' => 'new'],
            'created_at' => now()->subDays(10),
        ]);

        AuditLog::query()->create([
            'auditable_type' => 'Test',
            'auditable_id' => '1',
            'event' => 'updated',
            'old_values' => ['name' => 'new'],
            'new_values' => ['name' => 'latest'],
            'created_at' => now(),
        ]);

        $deleted = AuditLog::pruneOlderThan(5);

        $this->assertEquals(1, $deleted);
        $this->assertEquals(1, AuditLog::query()->count());
    }
}
