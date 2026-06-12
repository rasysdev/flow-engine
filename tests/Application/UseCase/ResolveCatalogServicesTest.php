<?php

namespace Tests\Application\UseCase;

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\InfraMap\Contract\CatalogLoader;
use FlowEngine\Application\InfraMap\Contract\DockerTopologyReader;
use FlowEngine\Application\UseCase\ResolveCatalogServices;

/**
 * @covers \FlowEngine\Application\UseCase\ResolveCatalogServices
 */
final class ResolveCatalogServicesTest extends TestCase
{
    public function test_enriched_entries_merges_docker_hostnames_and_keeps_entry_shape(): void
    {
        $catalog = [
            'baseDir' => '/srv',
            'entries' => [
                ['path' => '/srv/a', 'name' => 'a', 'hostnames' => ['keep.local']],
                ['path' => '/srv/b', 'name' => 'b'],
            ],
        ];
        $docker = [
            'serviceMappings' => [
                ['service' => 'a', 'hostnames' => ['a1.local']],
                ['service' => 'b', 'hostnames' => ['b1.local']],
            ],
        ];

        $loader = $this->createMock(CatalogLoader::class);
        $loader->expects($this->once())->method('load')
            ->with('/cat.json', null)
            ->willReturn($catalog);
        $reader = $this->createMock(DockerTopologyReader::class);
        $reader->expects($this->once())->method('analyze')
            ->with('/srv', $catalog['entries'])
            ->willReturn($docker);

        $result = (new ResolveCatalogServices($loader, $reader))->enrichedEntries('/cat.json');

        $this->assertSame('/srv/a', $result[0]['path']);
        $this->assertSame('a', $result[0]['name']);
        $this->assertSame(['keep.local', 'a1.local'], $result[0]['hostnames']);
        $this->assertSame(['b1.local'], $result[1]['hostnames']);
    }

    public function test_enriched_entries_returns_empty_when_catalog_is_invalid(): void
    {
        $loader = $this->createStub(CatalogLoader::class);
        $loader->method('load')->willReturn(null);
        $reader = $this->createMock(DockerTopologyReader::class);
        $reader->expects($this->never())->method('analyze');

        $result = (new ResolveCatalogServices($loader, $reader))->enrichedEntries('/missing.json');

        $this->assertSame([], $result);
    }

    public function test_enriched_entries_with_docker_loads_catalog_once_and_returns_both(): void
    {
        $catalog = [
            'baseDir' => '/srv',
            'entries' => [
                ['path' => '/srv/a', 'name' => 'a', 'hostnames' => ['keep.local']],
                ['path' => '/srv/b', 'name' => 'b'],
            ],
        ];
        $docker = [
            'containers' => [['name' => 'web']],
            'serviceMappings' => [
                ['service' => 'a', 'hostnames' => ['a1.local']],
            ],
        ];

        $loader = $this->createMock(CatalogLoader::class);
        $loader->expects($this->once())->method('load')
            ->with('/cat.json', null)
            ->willReturn($catalog);
        $reader = $this->createMock(DockerTopologyReader::class);
        $reader->expects($this->once())->method('analyze')
            ->with('/srv', $catalog['entries'])
            ->willReturn($docker);

        $result = (new ResolveCatalogServices($loader, $reader))->enrichedEntriesWithDocker('/cat.json');

        $this->assertSame(['keep.local', 'a1.local'], $result['entries'][0]['hostnames']);
        $this->assertSame('b', $result['entries'][1]['name']);
        $this->assertSame($docker, $result['docker']);
    }

    public function test_enriched_entries_with_docker_returns_empty_shape_when_catalog_is_invalid(): void
    {
        $loader = $this->createStub(CatalogLoader::class);
        $loader->method('load')->willReturn(null);
        $reader = $this->createMock(DockerTopologyReader::class);
        $reader->expects($this->never())->method('analyze');

        $result = (new ResolveCatalogServices($loader, $reader))->enrichedEntriesWithDocker('/missing.json');

        $this->assertSame([], $result['entries']);
        $this->assertSame([], $result['docker']['containers']);
        $this->assertSame([], $result['docker']['serviceMappings']);
        $this->assertArrayHasKey('warnings', $result['docker']);
    }
}
