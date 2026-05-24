<?php

namespace Tests\Application\DTO;

use FlowEngine\Application\DTO\ArchitectureReportDTO;
use FlowEngine\Application\DTO\ComplexityReportDTO;
use FlowEngine\Application\DTO\CycleReportDTO;
use FlowEngine\Application\DTO\MetricsReportDTO;
use FlowEngine\Application\DTO\OrphanReportDTO;
use PHPUnit\Framework\TestCase;

final class AnalysisReportDTOTest extends TestCase
{
    public function test_complexity_report_serialization(): void
    {
        $dto = new ComplexityReportDTO(
            totalMethods: 12,
            avgComplexity: 3.5,
            maxComplexity: 10,
            minComplexity: 1,
            byLevel: ['LOW' => 10, 'MEDIUM' => 2, 'HIGH' => 0, 'CRITICAL' => 0],
            complexMethods: [
                [
                    'nodeId' => 'App\\Service::run',
                    'complexity' => 12,
                    'level' => 'HIGH',
                    'file' => '/path/Service.php',
                    'line' => 120,
                ],
            ]
        );

        $array = $dto->toArray();
        $this->assertEquals(12, $array['totalMethods']);
        $this->assertEquals(3.5, $array['avgComplexity']);
        $this->assertCount(1, $array['complexMethods']);

        $json = $dto->toJson();
        $decoded = json_decode($json, true);
        $this->assertEquals(10, $decoded['maxComplexity']);
    }

    public function test_cycle_report_serialization(): void
    {
        $dto = new CycleReportDTO(
            totalCycles: 2,
            totalNodesInCycles: 5,
            bySeverity: ['LOW' => 1, 'MEDIUM' => 1, 'HIGH' => 0, 'CRITICAL' => 0],
            largestCycle: 3,
            cycles: [
                ['nodes' => ['A', 'B'], 'size' => 2, 'severity' => 'LOW'],
            ]
        );

        $array = $dto->toArray();
        $this->assertEquals(2, $array['totalCycles']);
        $this->assertEquals(3, $array['largestCycle']);

        $json = $dto->toJson();
        $decoded = json_decode($json, true);
        $this->assertEquals(5, $decoded['totalNodesInCycles']);
    }

    public function test_architecture_report_serialization(): void
    {
        $dto = new ArchitectureReportDTO(
            isClean: false,
            totalViolations: 1,
            bySeverity: ['CRITICAL' => 1, 'HIGH' => 0],
            byType: ['Domain -> Infrastructure' => 1, 'Domain -> Application' => 0, 'Application -> Infrastructure' => 0],
            layerDistribution: ['Domain' => 2, 'Application' => 1, 'Infrastructure' => 1, 'Unknown' => 0],
            violations: [
                [
                    'from' => 'Domain\\Service::run',
                    'to' => 'Infrastructure\\Repo::save',
                    'fromLayer' => 'Domain',
                    'toLayer' => 'Infrastructure',
                    'severity' => 'CRITICAL',
                    'reason' => 'Domain layer depends on Infrastructure',
                ],
            ]
        );

        $array = $dto->toArray();
        $this->assertFalse($array['isClean']);
        $this->assertEquals(1, $array['totalViolations']);

        $json = $dto->toJson();
        $decoded = json_decode($json, true);
        $this->assertEquals('CRITICAL', $decoded['violations'][0]['severity']);
    }

    public function test_metrics_report_serialization(): void
    {
        $dto = new MetricsReportDTO(
            totalNodes: 3,
            totalEdges: 2,
            avgFanIn: 1.5,
            avgFanOut: 1.0,
            maxFanIn: 2,
            maxFanOut: 2,
            hotspotCount: 1,
            hotspots: [
                [
                    'nodeId' => 'App\\Service::run',
                    'fanIn' => 2,
                    'fanOut' => 1,
                    'blastRadius' => 0,
                    'riskLevel' => 'HIGH',
                    'complexityScore' => 7,
                    'isHotspot' => true,
                ],
            ],
            topCoupled: [
                [
                    'nodeId' => 'App\\Service::run',
                    'fanIn' => 2,
                    'fanOut' => 1,
                    'blastRadius' => 0,
                    'riskLevel' => 'HIGH',
                    'complexityScore' => 7,
                    'isHotspot' => true,
                ],
            ]
        );

        $array = $dto->toArray();
        $this->assertEquals(3, $array['totalNodes']);
        $this->assertEquals(1, $array['hotspotCount']);

        $json = $dto->toJson();
        $decoded = json_decode($json, true);
        $this->assertEquals(2, $decoded['maxFanIn']);
    }

    public function test_orphan_report_serialization(): void
    {
        $dto = new OrphanReportDTO(
            totalOrphans: 2,
            highConfidenceOrphans: 1,
            suspiciousLeafNodes: 1,
            percentageOrphans: 10.5,
            orphans: [
                [
                    'nodeId' => 'App\\Service::unused',
                    'reason' => 'Never called',
                    'confidence' => 0.8,
                    'confidencePercentage' => 80,
                    'confidenceLevel' => 'VERY_HIGH',
                    'safeToRemove' => true,
                ],
            ],
            suspiciousLeaves: [
                [
                    'nodeId' => 'App\\Service::leaf',
                    'reason' => 'Leaf node with no apparent utility purpose',
                    'confidence' => 0.4,
                    'confidencePercentage' => 40,
                    'confidenceLevel' => 'MEDIUM',
                    'safeToRemove' => false,
                ],
            ]
        );

        $array = $dto->toArray();
        $this->assertEquals(2, $array['totalOrphans']);
        $this->assertEquals(10.5, $array['percentageOrphans']);

        $json = $dto->toJson();
        $decoded = json_decode($json, true);
        $this->assertEquals(1, $decoded['highConfidenceOrphans']);
    }
}
