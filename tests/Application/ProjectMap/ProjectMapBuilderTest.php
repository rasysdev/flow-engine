<?php

namespace Tests\Application\ProjectMap;

use FlowEngine\Application\ProjectMap\ProjectMapBuilder;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

class ProjectMapBuilderTest extends TestCase
{
    public function test_typescript_api_helper_class_is_not_classified_as_http_entrypoint(): void
    {
        $builder = new ProjectMapBuilder();
        $node = new Node(
            'apps.server.src.environments.remote.api.RemoteEnvironmentAuthHttpError',
            'constructor',
            '/repo/apps/server/src/environments/remote/api.ts',
            1,
            'typescript'
        );

        self::assertSame('custom', $builder->classifyEntrypoint($node));
    }

    public function test_typescript_server_file_only_marks_bootstrap_methods_as_cli(): void
    {
        $builder = new ProjectMapBuilder();

        self::assertSame('custom', $builder->classifyEntrypoint(new Node(
            'apps.api.src.server.Server',
            'broadcast',
            '/repo/apps/api/src/server.ts',
            1,
            'typescript'
        )));

        self::assertSame('cli', $builder->classifyEntrypoint(new Node(
            'apps.api.src.server.Server',
            'start',
            '/repo/apps/api/src/server.ts',
            1,
            'typescript'
        )));
    }
}
