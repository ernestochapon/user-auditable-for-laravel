<?php

namespace ErnestoChapon\UserAuditable\Tests\Unit;

use ErnestoChapon\UserAuditable\Providers\UserAuditableServiceProvider;
use ErnestoChapon\UserAuditable\Tests\TestCase;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;

class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_registers_the_service_provider(): void
    {
        $providers = $this->app->getLoadedProviders();

        $this->assertArrayHasKey(
            'ErnestoChapon\\UserAuditable\\Providers\\UserAuditableServiceProvider',
            $providers
        );
    }

    #[Test]
    public function it_registers_all_schema_macros(): void
    {
        $macros = [
            'userAuditable',
            'dropUserAuditable',
            'uuidColumn',
            'ulidColumn',
            'statusColumn',
            'fullAuditable',
            'eventAuditable',
            'dropEventAuditable',
            'auditLogTable',
            'dropAuditLogTable',
        ];

        foreach ($macros as $macro) {
            $this->assertTrue(
                Blueprint::hasMacro($macro),
                "Macro [{$macro}] is not registered."
            );
        }
    }

    #[Test]
    public function it_provides_config_file(): void
    {
        $config = config('user-auditable');

        $this->assertIsArray($config);
        $this->assertArrayHasKey('enabled_macros', $config);
        $this->assertArrayHasKey('change_tracking', $config);
    }

    #[Test]
    public function it_throws_when_full_auditable_enabled_without_user_auditable(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The [full_auditable] macro requires [user_auditable]');

        config(['user-auditable.enabled_macros' => ['full_auditable']]);

        $provider = new UserAuditableServiceProvider($this->app);
        $provider->boot();
    }
}
