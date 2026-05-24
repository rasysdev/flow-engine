<?php

namespace FlowEngine\Application\UseCase;

use FlowEngine\Application\UseCase\DTO\NodeLookupResultDTO;
use FlowEngine\Domain\Contracts\FlowRepository;

final class LookupNodesBySymbol
{
    public function __construct(
        private FlowRepository $repository
    ) {
    }

    public function execute(string $query, ?string $type = null, int $limit = 10): NodeLookupResultDTO
    {
        if ($query === '') {
            return new NodeLookupResultDTO(query: $query, matches: []);
        }

        $flow = $this->repository->getFlow();
        $nodes = $flow->nodes();
        $queryLower = strtolower($query);

        // Build fan maps
        $fanInMap = [];
        $fanOutMap = [];
        foreach ($flow->edges() as $edge) {
            $from = $edge->from();
            $to   = $edge->to();
            $fanOutMap[$from] = ($fanOutMap[$from] ?? 0) + 1;
            $fanInMap[$to]    = ($fanInMap[$to] ?? 0) + 1;
        }

        $matches = [];

        if ($type === 'class' || $type === null) {
            // Group nodes by class
            $byClass = [];
            foreach ($nodes as $node) {
                $byClass[$node->class()][] = $node;
            }

            foreach ($byClass as $class => $classNodes) {
                if (strpos(strtolower($class), $queryLower) === false) {
                    continue;
                }
                if ($type !== null && $type !== 'class') {
                    continue;
                }

                $methods = array_map(fn($n) => $n->method(), $classNodes);
                $firstNode = $classNodes[0];

                // Aggregate fan_in / fan_out across all methods of the class
                $classFanIn = 0;
                $classFanOut = 0;
                foreach ($classNodes as $n) {
                    $classFanIn  += $fanInMap[$n->id()]  ?? 0;
                    $classFanOut += $fanOutMap[$n->id()] ?? 0;
                }

                $match = [
                    'type'    => 'class',
                    'id'      => $class,
                    'file'    => $firstNode->file(),
                    'methods' => $methods,
                    'fan_in'  => $classFanIn,
                    'fan_out' => $classFanOut,
                ];
                $matches[] = $match;

                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        if (($type === 'method' || $type === null) && count($matches) < $limit) {
            foreach ($nodes as $node) {
                if (count($matches) >= $limit) {
                    break;
                }

                if (strpos(strtolower($node->method()), $queryLower) === false) {
                    continue;
                }

                // Skip if already found as class match
                if ($type === null) {
                    $alreadyMatched = false;
                    foreach ($matches as $m) {
                        if ($m['id'] === $node->class() && $m['type'] === 'class') {
                            $alreadyMatched = true;
                            break;
                        }
                    }
                    if ($alreadyMatched) {
                        continue;
                    }
                }

                $matches[] = [
                    'type'    => 'method',
                    'id'      => $node->id(),
                    'file'    => $node->file(),
                    'fan_in'  => $fanInMap[$node->id()]  ?? 0,
                    'fan_out' => $fanOutMap[$node->id()] ?? 0,
                ];
            }
        }

        if (($type === 'namespace' || $type === null) && count($matches) < $limit) {
            $byNs = [];
            foreach ($nodes as $node) {
                $class = $node->class();
                $lastSep = strrpos($class, '\\');
                $ns = $lastSep !== false ? substr($class, 0, $lastSep) : '';
                if ($ns === '') {
                    continue;
                }
                $byNs[$ns][] = $node;
            }

            foreach ($byNs as $ns => $nsNodes) {
                if (count($matches) >= $limit) {
                    break;
                }

                if (strpos(strtolower($ns), $queryLower) === false) {
                    continue;
                }

                $nsFanIn = 0;
                $nsFanOut = 0;
                foreach ($nsNodes as $n) {
                    $nsFanIn  += $fanInMap[$n->id()]  ?? 0;
                    $nsFanOut += $fanOutMap[$n->id()] ?? 0;
                }

                $matches[] = [
                    'type'    => 'namespace',
                    'id'      => $ns,
                    'fan_in'  => $nsFanIn,
                    'fan_out' => $nsFanOut,
                ];
            }
        }

        return new NodeLookupResultDTO(query: $query, matches: $matches);
    }
}
