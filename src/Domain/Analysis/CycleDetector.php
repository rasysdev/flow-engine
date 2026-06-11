<?php

namespace FlowEngine\Domain\Analysis;

use FlowEngine\Domain\Contracts\Flow;

/**
 * Detecta ciclos de dependência no grafo usando algoritmo de Tarjan.
 * 
 * Strongly Connected Components (SCC):
 * - Um SCC é um conjunto de nodes onde todo node pode alcançar todo outro node
 * - Se SCC tem mais de 1 node = CICLO DE DEPENDÊNCIA
 * 
 * Algoritmo de Tarjan:
 * - Complexidade: O(V + E) - muito eficiente!
 * - DFS com stack pra rastrear SCCs
 * 
 * Severidade dos ciclos:
 * - 2 nodes: LOW (ciclo simples)
 * - 3-5 nodes: MEDIUM
 * - 6-10 nodes: HIGH
 * - 11+ nodes: CRITICAL (spaghetti code!)
 */
final class CycleDetector
{
    private int $index = 0;
    private array $stack = [];
    private array $nodeIndex = [];
    private array $nodeLowLink = [];
    private array $onStack = [];
    private array $sccs = [];
    
    /** @var array<string, string[]> Adjacency list */
    private array $adjacencyList = [];

    public function __construct(
        private Flow $flow
    ) {
        $this->buildAdjacencyList();
    }

    /**
     * Detecta todos os ciclos de dependência.
     * 
     * @internal 
     * @return array<int, array{nodes: string[], size: int, severity: string}>
     */
    public function detectCycles(): array
    {
        $this->reset();
        
        // Executar Tarjan em todos os nodes
        foreach (array_keys($this->adjacencyList) as $nodeId) {
            if (!isset($this->nodeIndex[$nodeId])) {
                $this->tarjanSCC($nodeId);
            }
        }
        
        // Filtrar apenas SCCs que são ciclos (size > 1)
        $cycles = [];
        
        foreach ($this->sccs as $scc) {
            if (count($scc) > 1) {
                $intraClass = $this->isIntraClassCycle($scc);
                $cycles[] = [
                    'nodes' => $scc,
                    'size' => count($scc),
                    'severity' => $intraClass ? 'INFO' : $this->calculateSeverity(count($scc)),
                    'reason' => $intraClass ? 'intra-class mutual recursion' : null,
                ];
            }
        }
        
        // Ordenar por tamanho (maiores primeiro)
        usort($cycles, fn($a, $b) => $b['size'] <=> $a['size']);
        
        return $cycles;
    }

    /**
     * Algoritmo de Tarjan para encontrar SCCs.
     */
    private function tarjanSCC(string $nodeId): void
    {
        // Inicializar node
        $this->nodeIndex[$nodeId] = $this->index;
        $this->nodeLowLink[$nodeId] = $this->index;
        $this->index++;
        $this->stack[] = $nodeId;
        $this->onStack[$nodeId] = true;
        
        // Explorar vizinhos
        if (isset($this->adjacencyList[$nodeId])) {
            foreach ($this->adjacencyList[$nodeId] as $neighbor) {
                if (!isset($this->nodeIndex[$neighbor])) {
                    // Neighbor não visitado
                    $this->tarjanSCC($neighbor);
                    $this->nodeLowLink[$nodeId] = min(
                        $this->nodeLowLink[$nodeId],
                        $this->nodeLowLink[$neighbor]
                    );
                } elseif ($this->onStack[$neighbor] ?? false) {
                    // Neighbor está na stack = parte do SCC atual
                    $this->nodeLowLink[$nodeId] = min(
                        $this->nodeLowLink[$nodeId],
                        $this->nodeIndex[$neighbor]
                    );
                }
            }
        }
        
        // Se é root do SCC, pop a stack
        if ($this->nodeLowLink[$nodeId] === $this->nodeIndex[$nodeId]) {
            $scc = [];
            
            do {
                $w = array_pop($this->stack);
                $this->onStack[$w] = false;
                $scc[] = $w;
            } while ($w !== $nodeId);
            
            $this->sccs[] = $scc;
        }
    }

    /**
     * Constrói adjacency list a partir das edges.
     */
    private function buildAdjacencyList(): void
    {
        // Inicializar todos os nodes
        foreach ($this->flow->nodes() as $node) {
            $this->adjacencyList[$node->id()] = [];
        }
        
        // Adicionar edges
        foreach ($this->flow->edges() as $edge) {
            $from = $edge->from();
            $to = $edge->to();
            
            if (!isset($this->adjacencyList[$from])) {
                $this->adjacencyList[$from] = [];
            }
            
            $this->adjacencyList[$from][] = $to;
        }
    }

    /**
     * Reset do estado interno.
     */
    private function reset(): void
    {
        $this->index = 0;
        $this->stack = [];
        $this->nodeIndex = [];
        $this->nodeLowLink = [];
        $this->onStack = [];
        $this->sccs = [];
    }

    /**
     * True when every node in the cycle belongs to the same class — mutual
     * recursion within one class (e.g. a recursive parser), a legitimate
     * pattern rather than a cross-class architectural cycle.
     *
     * @param string[] $scc
     */
    private function isIntraClassCycle(array $scc): bool
    {
        $classes = array_map(
            static fn(string $nodeId): string => explode('::', $nodeId)[0],
            $scc
        );

        return count(array_unique($classes)) === 1;
    }

    /**
     * Calcula severidade baseado no tamanho do ciclo.
     */
    private function calculateSeverity(int $size): string
    {
        if ($size >= 11) {
            return 'CRITICAL'; // Spaghetti code
        }
        
        if ($size >= 6) {
            return 'HIGH';
        }
        
        if ($size >= 3) {
            return 'MEDIUM';
        }
        
        return 'LOW'; // Ciclo simples (2 nodes)
    }

    /**
     * Estatísticas sobre ciclos.
     * 
     * @api
     */
    public function stats(): array
    {
        $cycles = $this->detectCycles();
        
        $bySeverity = [
            'INFO' => 0,
            'LOW' => 0,
            'MEDIUM' => 0,
            'HIGH' => 0,
            'CRITICAL' => 0,
        ];
        
        $totalNodesInCycles = 0;
        
        foreach ($cycles as $cycle) {
            $bySeverity[$cycle['severity']]++;
            $totalNodesInCycles += $cycle['size'];
        }
        
        return [
            'totalCycles' => count($cycles),
            'totalNodesInCycles' => $totalNodesInCycles,
            'bySeverity' => $bySeverity,
            'largestCycle' => count($cycles) > 0 ? $cycles[0]['size'] : 0,
        ];
    }

    /**
     * Verifica se dois nodes específicos estão em um ciclo.
     */
    public function areInCycle(string $nodeA, string $nodeB): bool
    {
        $cycles = $this->detectCycles();
        
        foreach ($cycles as $cycle) {
            if (in_array($nodeA, $cycle['nodes']) && in_array($nodeB, $cycle['nodes'])) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Retorna o ciclo que contém um node específico.
     * 
     * @return array|null
     */
    public function getCycleContaining(string $nodeId): ?array
    {
        $cycles = $this->detectCycles();
        
        foreach ($cycles as $cycle) {
            if (in_array($nodeId, $cycle['nodes'])) {
                return $cycle;
            }
        }
        
        return null;
    }
}