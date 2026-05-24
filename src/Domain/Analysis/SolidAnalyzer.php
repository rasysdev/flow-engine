<?php

namespace FlowEngine\Domain\Analysis;

use FlowEngine\Domain\Contracts\Flow;

/**
 * Detecta violações dos princípios SOLID usando o grafo de dependências.
 *
 * Princípios analisados:
 *
 * S — Single Responsibility Principle
 *   Detecta classes com fan-out excessivo (dependem de muitas outras classes).
 *   Alto fan-out por classe sugere múltiplas responsabilidades acopladas.
 *
 * O — Open/Closed Principle
 *   Detecta oportunidades: classes com muitas instanciações diretas de concretos
 *   sugerem que polimorfismo / Strategy poderia ser aplicado.
 *
 * I — Interface Segregation Principle
 *   Detecta interfaces com muitos métodos (classes cujo nome termina em Interface
 *   ou Contract com contagem de métodos acima do limiar).
 *
 * D — Dependency Inversion Principle
 *   Detecta instâncias diretas via `new ConcreteClass()` em camadas que deveriam
 *   depender de abstrações (Domain, Application). Requer edges `direct_instantiation`
 *   geradas pelo NewInstantiationVisitor.
 *
 * L — Liskov Substitution Principle
 *   Não detectável de forma confiável via análise estática de grafo; omitido.
 *
 * @api
 */
final class SolidAnalyzer
{
    /** Fan-out de classes únicas acima deste limiar indica SRP concern */
    private const SRP_FAN_OUT_THRESHOLD = 7;

    /** Interfaces com mais métodos que este limiar indicam ISP concern */
    private const ISP_METHOD_THRESHOLD = 5;

    /** Camadas que devem depender de abstrações (para DIP) */
    private const DIP_PROTECTED_LAYERS = ['Domain', 'Application'];

    public function __construct(private readonly Flow $flow)
    {
    }

    /**
     * Executa todas as análises SOLID.
     *
     * @api
     * @return array{
     *   srp: array<int, array{class: string, fanOut: int, severity: string, reason: string}>,
     *   isp: array<int, array{interface: string, methodCount: int, methods: string[], severity: string, reason: string}>,
     *   dip: array<int, array{from: string, to: string, fromLayer: string, severity: string, reason: string}>,
     *   ocp: array<int, array{class: string, directInstantiations: int, severity: string, reason: string}>
     * }
     */
    public function analyze(): array
    {
        return [
            'srp' => $this->detectSrpViolations(),
            'isp' => $this->detectIspViolations(),
            'dip' => $this->detectDipViolations(),
            'ocp' => $this->detectOcpOpportunities(),
        ];
    }

    /**
     * S — Single Responsibility Principle.
     *
     * Agrupa edges por classe de origem e conta quantas classes distintas ela alcança.
     * Se o fan-out de classe > threshold, é um sinal de responsabilidades excessivas.
     *
     * @return array<int, array{class: string, fanOut: int, severity: string, reason: string}>
     */
    public function detectSrpViolations(): array
    {
        /** @var array<string, array<string, true>> $classTargets class → set of target classes */
        $classTargets = [];

        foreach ($this->flow->edges() as $edge) {
            $fromClass = $this->extractClass($edge->from());
            $toClass   = $this->extractClass($edge->to());

            if ($fromClass === null || $toClass === null || $fromClass === $toClass) {
                continue;
            }

            // Ignorar nós sintéticos (model, interface, laravel, etc.)
            if ($this->isSyntheticNode($edge->to())) {
                continue;
            }

            $classTargets[$fromClass][$toClass] = true;
        }

        $violations = [];
        foreach ($classTargets as $class => $targets) {
            $fanOut = count($targets);
            if ($fanOut <= self::SRP_FAN_OUT_THRESHOLD) {
                continue;
            }

            $violations[] = [
                'class'    => $class,
                'fanOut'   => $fanOut,
                'severity' => $fanOut > 12 ? 'HIGH' : 'MEDIUM',
                'reason'   => "Class depends on {$fanOut} different classes (threshold: "
                    . self::SRP_FAN_OUT_THRESHOLD
                    . '). Consider extracting cohesive responsibilities into separate classes.',
            ];
        }

        usort($violations, fn($a, $b) => $b['fanOut'] <=> $a['fanOut']);

        return $violations;
    }

    /**
     * I — Interface Segregation Principle.
     *
     * Procura classes cujo nome sugere que são interfaces (sufixo Interface/Contract)
     * e conta seus métodos no grafo. Interfaces com muitos métodos violam ISP.
     *
     * @return array<int, array{interface: string, methodCount: int, methods: string[], severity: string, reason: string}>
     */
    public function detectIspViolations(): array
    {
        /** @var array<string, list<string>> $interfaceMethods FQN → list of method names */
        $interfaceMethods = [];

        foreach ($this->flow->nodes() as $node) {
            $class  = $this->extractClass($node->id());
            $method = $this->extractMethod($node->id());

            if ($class === null || $method === null) {
                continue;
            }

            // Ignorar nós sintéticos e métodos mágicos
            if ($this->isSyntheticMethod($method)) {
                continue;
            }

            if ($this->looksLikeInterface($class)) {
                $interfaceMethods[$class][] = $method;
            }
        }

        $violations = [];
        foreach ($interfaceMethods as $interface => $methods) {
            $count = count($methods);
            if ($count <= self::ISP_METHOD_THRESHOLD) {
                continue;
            }

            $violations[] = [
                'interface'   => $interface,
                'methodCount' => $count,
                'methods'     => $methods,
                'severity'    => $count > 10 ? 'HIGH' : 'MEDIUM',
                'reason'      => "Interface has {$count} methods (threshold: "
                    . self::ISP_METHOD_THRESHOLD
                    . '). Consider splitting into smaller, focused interfaces (ISP).',
            ];
        }

        usort($violations, fn($a, $b) => $b['methodCount'] <=> $a['methodCount']);

        return $violations;
    }

    /**
     * D — Dependency Inversion Principle.
     *
     * Detecta edges de tipo `direct_instantiation` que saem de camadas protegidas
     * (Domain, Application) para classes concretas. Instanciar um concreto
     * nessas camadas quebra o DIP.
     *
     * Requer o NewInstantiationVisitor no pipeline do AstParser.
     *
     * @return array<int, array{from: string, to: string, fromLayer: string, severity: string, reason: string}>
     */
    public function detectDipViolations(): array
    {
        $violations = [];
        $seen       = [];

        foreach ($this->flow->edges() as $edge) {
            if ($edge->type() !== 'direct_instantiation') {
                continue;
            }

            $key = $edge->from() . '|' . $edge->to();
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            $fromClass = $this->extractClass($edge->from());
            $toClass   = $this->extractClass($edge->to());

            if ($fromClass === null || $toClass === null) {
                continue;
            }

            $fromLayer = $this->inferLayer($fromClass);

            // Só nos importa violação em camadas que devem depender de abstrações
            if (!in_array($fromLayer, self::DIP_PROTECTED_LAYERS, true)) {
                continue;
            }

            // Se o alvo é um concreto (não interface, não VO/DTO), é violação
            if (!$this->looksLikeInterface($toClass)) {
                $violations[] = [
                    'from'      => $edge->from(),
                    'to'        => $edge->to(),
                    'fromLayer' => $fromLayer,
                    'severity'  => $fromLayer === 'Domain' ? 'HIGH' : 'MEDIUM',
                    'reason'    => "'{$fromClass}' ({$fromLayer}) directly instantiates concrete class"
                        . " '{$toClass}'. Inject an interface/abstraction instead (DIP).",
                ];
            }
        }

        return $violations;
    }

    /**
     * O — Open/Closed Principle (oportunidades).
     *
     * Detecta classes que instanciam diretamente muitas classes concretas distintas —
     * padrão que sugere que polimorfismo ou o padrão Strategy poderia ser aplicado.
     *
     * @return array<int, array{class: string, directInstantiations: int, severity: string, reason: string}>
     */
    public function detectOcpOpportunities(): array
    {
        /** @var array<string, array<string, true>> $classInstantiates */
        $classInstantiates = [];

        foreach ($this->flow->edges() as $edge) {
            if ($edge->type() !== 'direct_instantiation') {
                continue;
            }

            $fromClass = $this->extractClass($edge->from());
            $toClass   = $this->extractClass($edge->to());

            if ($fromClass === null || $toClass === null || $fromClass === $toClass) {
                continue;
            }

            $classInstantiates[$fromClass][$toClass] = true;
        }

        $suggestions = [];
        foreach ($classInstantiates as $class => $instantiated) {
            $count = count($instantiated);
            if ($count < 3) {
                continue;
            }

            $suggestions[] = [
                'class'                => $class,
                'directInstantiations' => $count,
                'severity'             => 'LOW',
                'reason'               => "'{$class}' directly instantiates {$count} different concrete classes."
                    . ' Consider using polymorphism, Strategy or Factory to open for extension without modification (OCP).',
            ];
        }

        usort($suggestions, fn($a, $b) => $b['directInstantiations'] <=> $a['directInstantiations']);

        return $suggestions;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function extractClass(string $nodeId): ?string
    {
        $parts = explode('::', $nodeId, 2);
        return $parts[0] !== '' ? $parts[0] : null;
    }

    private function extractMethod(string $nodeId): ?string
    {
        $parts = explode('::', $nodeId, 2);
        return $parts[1] ?? null;
    }

    private function isSyntheticNode(string $nodeId): bool
    {
        $method = $this->extractMethod($nodeId);
        return $method !== null && str_starts_with($method, '__');
    }

    private function isSyntheticMethod(string $method): bool
    {
        return str_starts_with($method, '__');
    }

    /**
     * Verifica se o nome da classe sugere ser uma interface/contrato.
     */
    private function looksLikeInterface(string $fqn): bool
    {
        $parts      = explode('\\', $fqn);
        $simpleName = end($parts);

        return str_ends_with($simpleName, 'Interface')
            || str_ends_with($simpleName, 'Contract')
            || str_ends_with($simpleName, 'Port');
    }

    /**
     * Infere a camada arquitetural a partir do namespace.
     */
    private function inferLayer(string $fqn): string
    {
        if (str_contains($fqn, '\\Domain\\')) {
            return 'Domain';
        }
        if (str_contains($fqn, '\\Application\\')) {
            return 'Application';
        }
        if (str_contains($fqn, '\\Infrastructure\\')) {
            return 'Infrastructure';
        }
        if (str_contains($fqn, '\\Interfaces\\')) {
            return 'Interfaces';
        }
        return 'Unknown';
    }
}
