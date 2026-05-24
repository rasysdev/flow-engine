<?php

namespace Tests\Application\UseCase;

use FlowEngine\Application\UseCase\AnalyzeArchitecture;
use FlowEngine\Application\UseCase\AnalyzeMetrics;
use FlowEngine\Application\UseCase\GenerateRemediationProposals;
use FlowEngine\Domain\Contracts\FlowRepository;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Flow;
use FlowEngine\Domain\Flow\Node;
use PHPUnit\Framework\TestCase;

final class GenerateRemediationProposalsTest extends TestCase
{
    public function test_deduplicates_architecture_proposals_for_equivalent_violations(): void
    {
        $flow = new Flow(
            [
                new Node('App\\Application\\A', 'run', __FILE__, 1),
                new Node('App\\Infrastructure\\B', 'call', __FILE__, 2),
            ],
            [
                new Edge('App\\Application\\A::run', 'App\\Infrastructure\\B::call', 'call'),
                new Edge('App\\Application\\A::run', 'App\\Infrastructure\\B::call', 'call'),
            ]
        );
        $repository = $this->repositoryWithFlow($flow);

        $useCase = new GenerateRemediationProposals(
            new AnalyzeArchitecture($repository),
            new AnalyzeMetrics($repository)
        );
        $report = $useCase->execute(10);

        $this->assertSame(1, $report->total);
        $this->assertSame('arch-001', $report->proposals[0]->id);
        $this->assertSame('App\\Application\\A::run -> App\\Infrastructure\\B::call', $report->proposals[0]->target);
    }

    private function repositoryWithFlow(Flow $flow): FlowRepository
    {
        return new class($flow) implements FlowRepository {
            public function __construct(private Flow $flow) {}

            public function analyze(): void
            {
            }

            public function getNodes(): array
            {
                return $this->flow->nodes();
            }

            public function getNode(string $id): Node
            {
                foreach ($this->flow->nodes() as $node) {
                    if ($node->id() === $id) {
                        return $node;
                    }
                }

                throw new \RuntimeException("Node not found: {$id}");
            }

            public function findNode(string $id): ?Node
            {
                foreach ($this->flow->nodes() as $node) {
                    if ($node->id() === $id) {
                        return $node;
                    }
                }

                return null;
            }

            public function getFlow(): \FlowEngine\Domain\Contracts\Flow
            {
                return $this->flow;
            }
        };
    }
}
