<?php

namespace Tests\Bootstrap;

use FlowEngine\Bootstrap\Container;
use FlowEngine\Application\UseCase\AnalyzeProject;
use PHPUnit\Framework\TestCase;

/**
 * @covers \FlowEngine\Bootstrap\Container
 */
final class ContainerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/flow-engine-container-test-' . uniqid();
        mkdir($this->tmpDir, 0o755, true);
    }

    protected function tearDown(): void
    {
        $json = $this->tmpDir . '/flow-engine.json';
        if (file_exists($json)) {
            unlink($json);
        }
        rmdir($this->tmpDir);
    }

    public function test_instancia_sem_flow_engine_json(): void
    {
        $container = new Container($this->tmpDir);

        $this->assertInstanceOf(Container::class, $container);
    }

    public function test_analyze_project_disponivel_sem_config(): void
    {
        $container = new Container($this->tmpDir);

        $this->assertInstanceOf(AnalyzeProject::class, $container->analyzeProject());
    }

    public function test_project_root_retorna_path_correto(): void
    {
        $container = new Container($this->tmpDir);

        $this->assertSame($this->tmpDir, $container->projectRoot());
    }
}
