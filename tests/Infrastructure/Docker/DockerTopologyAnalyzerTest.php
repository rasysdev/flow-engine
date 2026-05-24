<?php

namespace Tests\Infrastructure\Docker;

use FlowEngine\Infrastructure\Docker\DockerTopologyAnalyzer;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class DockerTopologyAnalyzerTest extends TestCase
{
    private ?string $tmpDir = null;

    protected function tearDown(): void
    {
        parent::tearDown();

        if ($this->tmpDir !== null && is_dir($this->tmpDir)) {
            $this->deleteDirectory($this->tmpDir);
        }
    }

    public function test_analyze_detects_mappings_networks_and_environments(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-engine-docker-topology-' . uniqid('', true);
        $this->tmpDir = $base;

        $catalogDir = $base . DIRECTORY_SEPARATOR . 'catalog';
        $svcA = $base . DIRECTORY_SEPARATOR . 'svc-a';
        $svcB = $base . DIRECTORY_SEPARATOR . 'svc-b';
        mkdir($catalogDir, 0777, true);
        mkdir($svcA, 0777, true);
        mkdir($svcB, 0777, true);

        file_put_contents($svcA . DIRECTORY_SEPARATOR . 'Dockerfile', "FROM php:8.2-cli\n");
        file_put_contents($catalogDir . DIRECTORY_SEPARATOR . '.env.production', "APP_ENV=production\n");
        file_put_contents($catalogDir . DIRECTORY_SEPARATOR . 'docker-compose.prod.yml', <<<YAML
services:
  svc-a:
    build:
      context: ../svc-a
      dockerfile: Dockerfile
    container_name: svc-a-prod
    env_file:
      - .env.production
    environment:
      APP_ENV: production
      SECRET_TOKEN: not-returned
    networks:
      appnet:
        aliases:
          - api.internal
    depends_on:
      - svc-b
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "php", "-v"]
      interval: 10s
      timeout: 5s
      retries: 3
    logging:
      driver: "json-file"
      options:
        max-size: "10m"
        max-file: "3"
  svc-b:
    image: python:3.12-slim
    environment:
      - STAGING_FLAG
      - WORKER_COUNT=2
    healthcheck: {}
    logging: {}
    profiles: [staging]
    networks:
      - appnet
networks:
  appnet: {}
YAML);

        $entries = [
            [
                'path' => $svcA,
                'name' => 'svc-a',
                'hostnames' => [],
                'contractEndpoints' => null,
                'docker' => [
                    'composeFiles' => [],
                    'dockerfiles' => [],
                    'envFiles' => [],
                    'serviceNames' => [],
                ],
            ],
            [
                'path' => $svcB,
                'name' => 'svc-b',
                'hostnames' => [],
                'contractEndpoints' => null,
                'docker' => [
                    'composeFiles' => [],
                    'dockerfiles' => [],
                    'envFiles' => [],
                    'serviceNames' => [],
                ],
            ],
        ];

        $result = (new DockerTopologyAnalyzer())->analyze($catalogDir, $entries);

        $this->assertContains($catalogDir . DIRECTORY_SEPARATOR . 'docker-compose.prod.yml', $result['detectedComposeFiles']);
        $this->assertContains($svcA . DIRECTORY_SEPARATOR . 'Dockerfile', $result['dockerfiles']);
        $this->assertContains($catalogDir . DIRECTORY_SEPARATOR . '.env.production', $result['environmentFiles']);
        $this->assertCount(2, $result['containers']);
        $this->assertCount(1, $result['networks']);

        $containerByName = [];
        foreach ($result['containers'] as $container) {
            $containerByName[$container['name']] = $container;
        }

        $this->assertSame('unless-stopped', $containerByName['svc-a']['restart']);
        $this->assertSame(['APP_ENV', 'SECRET_TOKEN'], $containerByName['svc-a']['environmentKeys']);
        $this->assertSame(['STAGING_FLAG', 'WORKER_COUNT'], $containerByName['svc-b']['environmentKeys']);
        $this->assertArrayHasKey('healthcheck', $containerByName['svc-a']);
        $this->assertNull($containerByName['svc-b']['healthcheck']);
        $this->assertNull($containerByName['svc-b']['logging']);
        $this->assertSame('json-file', $containerByName['svc-a']['logging']['driver']);

        $mappingByService = [];
        foreach ($result['serviceMappings'] as $mapping) {
            $mappingByService[$mapping['service']] = $mapping;
        }

        $this->assertSame(['svc-a'], $mappingByService['svc-a']['composeServices']);
        $this->assertContains('api.internal', $mappingByService['svc-a']['hostnames']);
        $this->assertContains('production', $mappingByService['svc-a']['environments']);
        $this->assertSame(['APP_ENV', 'SECRET_TOKEN'], $mappingByService['svc-a']['containers'][0]['environmentKeys']);
        $this->assertSame(['svc-b'], $mappingByService['svc-b']['composeServices']);
        $this->assertContains('staging', $mappingByService['svc-b']['environments']);
    }

    public function test_analyze_project_detects_recursive_compose_and_ignores_worktrees_and_dependencies(): void
    {
        $base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'flow-engine-docker-project-' . uniqid('', true);
        $this->tmpDir = $base;
        mkdir($base . DIRECTORY_SEPARATOR . 'deploy', 0777, true);
        mkdir($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'prod', 0777, true);
        mkdir($base . DIRECTORY_SEPARATOR . '.worktrees' . DIRECTORY_SEPARATOR . 'feature', 0777, true);
        mkdir($base . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'pkg', 0777, true);

        file_put_contents($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'Dockerfile.worker', "FROM alpine:3.19\n");
        file_put_contents($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'compose.worker.yml', <<<YAML
services:
  worker:
    build:
      context: .
      dockerfile: Dockerfile.worker
    depends_on:
      - redis
  redis:
    image: redis:7.2.4-alpine
YAML);
        file_put_contents($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'prod' . DIRECTORY_SEPARATOR . 'docker-compose.yml', <<<YAML
services:
  worker:
    image: alpine:3.20
YAML);
        file_put_contents($base . DIRECTORY_SEPARATOR . '.worktrees' . DIRECTORY_SEPARATOR . 'feature' . DIRECTORY_SEPARATOR . 'docker-compose.yml', <<<YAML
services:
  ignored:
    image: busybox:1.36.1
YAML);
        file_put_contents($base . DIRECTORY_SEPARATOR . 'node_modules' . DIRECTORY_SEPARATOR . 'pkg' . DIRECTORY_SEPARATOR . 'docker-compose.yml', <<<YAML
services:
  ignored-node:
    image: busybox:1.36.1
YAML);
        if (function_exists('symlink')) {
            @symlink($base, $base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'loop');
        }

        $result = (new DockerTopologyAnalyzer())->analyzeProject($base);

        $detected = implode("\n", $result['detectedComposeFiles']);
        $this->assertStringContainsString('compose.worker.yml', $detected);
        $this->assertStringNotContainsString('.worktrees', $detected);
        $this->assertStringNotContainsString('node_modules', $detected);
        $this->assertContains($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'Dockerfile.worker', $result['dockerfiles']);

        $containerNames = array_column($result['containers'], 'name');
        $this->assertContains('worker', $containerNames);
        $this->assertContains('redis', $containerNames);
        $this->assertNotContains('ignored', $containerNames);
        $this->assertNotContains('ignored-node', $containerNames);

        $workerComposeFiles = [];
        foreach ($result['containers'] as $container) {
            if ($container['name'] === 'worker') {
                $workerComposeFiles[] = $container['composeFile'];
            }
        }
        $this->assertCount(2, $workerComposeFiles);
        $this->assertContains($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'compose.worker.yml', $workerComposeFiles);
        $this->assertContains($base . DIRECTORY_SEPARATOR . 'deploy' . DIRECTORY_SEPARATOR . 'prod' . DIRECTORY_SEPARATOR . 'docker-compose.yml', $workerComposeFiles);
    }

    private function deleteDirectory(string $path): void
    {
        $items = scandir($path);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $current = $path . DIRECTORY_SEPARATOR . $item;
            if (is_link($current) || is_file($current)) {
                if (!unlink($current)) {
                    throw new RuntimeException("Cannot delete file: {$current}");
                }
                continue;
            }

            if (is_dir($current)) {
                $this->deleteDirectory($current);
                continue;
            }

            if (file_exists($current) && !unlink($current)) {
                throw new RuntimeException("Cannot delete file: {$current}");
            }
        }

        @rmdir($path);
    }
}
