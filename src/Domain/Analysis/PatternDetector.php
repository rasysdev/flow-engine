<?php

namespace FlowEngine\Domain\Analysis;

use FlowEngine\Domain\Contracts\Flow;

/**
 * Detecta padrões de projeto (Design Patterns) a partir do grafo de dependências.
 *
 * Padrões detectados:
 *
 * Repository
 *   Classe com sufixo `Repository` que possui métodos típicos de persistência
 *   (find*, get*, save, delete, update, all) — possivelmente pareada com interface.
 *
 * Factory
 *   Classe ou método com prefixo/sufixo Factory que possui métodos create/make/build.
 *   Detecta também métodos estáticos de criação em Value Objects.
 *
 * Strategy
 *   Interface com sufixo `Strategy` ou `Policy` + múltiplas classes que a implementam
 *   (detectado via edges de `interface_implementation`).
 *
 * Observer
 *   Classe com sufixo `Observer`, `Listener` ou `Subscriber` com métodos que correspondem
 *   a eventos de domínio ou hooks de modelo Eloquent.
 *
 * Command (padrão GoF, não Artisan)
 *   Classe com sufixo `Command`, `Handler` ou `Job` com método `handle` ou `execute`.
 *   Sugere o Command Pattern de encapsulamento de operações.
 *
 * Adapter
 *   Classe que implementa uma interface E injeta outra classe com nome diferente —
 *   sinal de que está adaptando uma interface para outra implementação.
 *
 * Decorator
 *   Classe que implementa uma interface E recebe no construtor outra instância
 *   da mesma interface (injeção de dependência circular no tipo).
 *
 * @api
 */
final class PatternDetector
{
    public function __construct(private readonly Flow $flow)
    {
    }

    /**
     * Detecta todos os padrões no grafo.
     *
     * @api
     * @return array{
     *   repository: list<array<string, mixed>>,
     *   factory: list<array<string, mixed>>,
     *   strategy: list<array<string, mixed>>,
     *   observer: list<array<string, mixed>>,
     *   command: list<array<string, mixed>>,
     *   adapter: list<array<string, mixed>>
     * }
     */
    public function detect(): array
    {
        return [
            'repository' => $this->detectRepository(),
            'factory'    => $this->detectFactory(),
            'strategy'   => $this->detectStrategy(),
            'observer'   => $this->detectObserver(),
            'command'    => $this->detectCommand(),
            'adapter'    => $this->detectAdapter(),
        ];
    }

    // -------------------------------------------------------------------------
    // Repository Pattern
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function detectRepository(): array
    {
        $classMethods = $this->groupMethodsByClass();
        $found        = [];

        foreach ($classMethods as $class => $methods) {
            $shortName = $this->shortName($class);

            if (!str_ends_with($shortName, 'Repository')) {
                continue;
            }

            $repoMethods = array_values(array_filter(
                $methods,
                fn($m) => $this->isRepositoryMethod($m)
            ));

            if (count($repoMethods) < 2) {
                continue;
            }

            $hasInterface = $this->hasMatchingInterface($class, 'Repository');

            $found[] = [
                'class'       => $class,
                'pattern'     => 'Repository',
                'methods'     => $repoMethods,
                'hasInterface' => $hasInterface,
                'confidence'  => $hasInterface ? 'HIGH' : 'MEDIUM',
                'note'        => $hasInterface
                    ? 'Correctly paired with an interface (DIP compliant).'
                    : 'No matching interface found. Consider extracting ' . $shortName . 'Interface.',
            ];
        }

        return $found;
    }

    private function isRepositoryMethod(string $method): bool
    {
        return str_starts_with($method, 'find')
            || str_starts_with($method, 'get')
            || str_starts_with($method, 'list')
            || str_starts_with($method, 'search')
            || in_array($method, ['save', 'delete', 'update', 'create', 'persist', 'remove', 'all', 'paginate'], true);
    }

    // -------------------------------------------------------------------------
    // Factory Pattern
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function detectFactory(): array
    {
        $classMethods = $this->groupMethodsByClass();
        $found        = [];

        foreach ($classMethods as $class => $methods) {
            $shortName = $this->shortName($class);

            $isFactoryClass  = str_ends_with($shortName, 'Factory') || str_contains($shortName, 'Factory');
            $factoryMethods  = array_values(array_filter($methods, fn($m) => $this->isFactoryMethod($m)));

            if (!$isFactoryClass && count($factoryMethods) < 2) {
                continue;
            }

            if ($factoryMethods === [] && $isFactoryClass) {
                continue; // Factory class mas sem métodos de criação — pode ser Eloquent factory
            }

            $found[] = [
                'class'          => $class,
                'pattern'        => 'Factory',
                'factoryMethods' => $factoryMethods,
                'confidence'     => $isFactoryClass ? 'HIGH' : 'MEDIUM',
                'note'           => $isFactoryClass
                    ? 'Named as Factory with creation methods.'
                    : 'Multiple creation methods detected. Consider extracting into a dedicated Factory.',
            ];
        }

        return $found;
    }

    private function isFactoryMethod(string $method): bool
    {
        return str_starts_with($method, 'create')
            || str_starts_with($method, 'make')
            || str_starts_with($method, 'build')
            || str_starts_with($method, 'new')
            || str_starts_with($method, 'from')
            || $method === 'of'
            || $method === 'for';
    }

    // -------------------------------------------------------------------------
    // Strategy Pattern
    // -------------------------------------------------------------------------

    /**
     * Detecta Strategy/Policy: interface com múltiplas implementações concretas.
     *
     * @return list<array<string, mixed>>
     */
    private function detectStrategy(): array
    {
        /** @var array<string, list<string>> interface FQN → list of implementing classes */
        $implementations = [];

        foreach ($this->flow->edges() as $edge) {
            if ($edge->type() !== 'interface_implementation') {
                continue;
            }

            // to: Interface::__interface
            $interface = $this->extractClass($edge->to());
            $from      = $this->extractClass($edge->from());

            if ($interface === null || $from === null) {
                continue;
            }

            $implementations[$interface][] = $from;
        }

        $found = [];
        foreach ($implementations as $interface => $implementors) {
            if (count($implementors) < 2) {
                continue;
            }

            $shortName = $this->shortName($interface);

            // Focar em interfaces que sugerem comportamento trocável
            $isStrategy = str_ends_with($shortName, 'Strategy')
                || str_ends_with($shortName, 'Policy')
                || str_ends_with($shortName, 'Handler')
                || str_ends_with($shortName, 'Processor')
                || str_ends_with($shortName, 'Calculator')
                || str_ends_with($shortName, 'Resolver')
                || str_ends_with($shortName, 'Provider')
                || str_ends_with($shortName, 'Adapter')
                || str_ends_with($shortName, 'Interface');

            if (!$isStrategy) {
                continue;
            }

            $found[] = [
                'interface'       => $interface,
                'pattern'         => 'Strategy',
                'implementations' => $implementors,
                'count'           => count($implementors),
                'confidence'      => 'HIGH',
                'note'            => count($implementors) . ' concrete implementations detected. '
                    . 'Clients depending on the interface can swap strategies at runtime.',
            ];
        }

        usort($found, fn($a, $b) => $b['count'] <=> $a['count']);

        return $found;
    }

    // -------------------------------------------------------------------------
    // Observer Pattern
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function detectObserver(): array
    {
        $classMethods = $this->groupMethodsByClass();
        $found        = [];

        $observerEvents = [
            'created', 'creating', 'updated', 'updating',
            'deleted', 'deleting', 'saved', 'saving',
            'restored', 'restoring', 'forceDeleted',
            'handle', 'onEvent', 'notify', 'update',
        ];

        foreach ($classMethods as $class => $methods) {
            $shortName = $this->shortName($class);

            $isObserver = str_ends_with($shortName, 'Observer')
                || str_ends_with($shortName, 'Listener')
                || str_ends_with($shortName, 'Subscriber');

            if (!$isObserver) {
                continue;
            }

            $eventMethods = array_values(array_filter(
                $methods,
                fn($m) => in_array($m, $observerEvents, true) || str_starts_with($m, 'on')
            ));

            if ($eventMethods === []) {
                continue;
            }

            $found[] = [
                'class'       => $class,
                'pattern'     => 'Observer',
                'subtype'     => str_ends_with($shortName, 'Subscriber') ? 'EventSubscriber' : 'Observer',
                'eventMethods' => $eventMethods,
                'confidence'  => 'HIGH',
                'note'        => 'Reacts to ' . count($eventMethods) . ' event(s): ' . implode(', ', $eventMethods) . '.',
            ];
        }

        return $found;
    }

    // -------------------------------------------------------------------------
    // Command Pattern (GoF)
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    private function detectCommand(): array
    {
        $classMethods = $this->groupMethodsByClass();
        $found        = [];

        foreach ($classMethods as $class => $methods) {
            $shortName = $this->shortName($class);

            $isCommand = str_ends_with($shortName, 'Command')
                || str_ends_with($shortName, 'Job')
                || str_ends_with($shortName, 'Handler')
                || str_ends_with($shortName, 'Action');

            if (!$isCommand) {
                continue;
            }

            $hasExecutor = in_array('handle', $methods, true)
                || in_array('execute', $methods, true)
                || in_array('__invoke', $methods, true);

            if (!$hasExecutor) {
                continue;
            }

            $executor = in_array('handle', $methods, true) ? 'handle'
                : (in_array('execute', $methods, true) ? 'execute' : '__invoke');

            $found[] = [
                'class'      => $class,
                'pattern'    => 'Command',
                'executor'   => $executor,
                'confidence' => 'HIGH',
                'note'       => "Encapsulates an operation via '{$executor}()'. "
                    . 'Supports queuing, retry, and undo if needed.',
            ];
        }

        return $found;
    }

    // -------------------------------------------------------------------------
    // Adapter Pattern
    // -------------------------------------------------------------------------

    /**
     * Detecta Adapters: classes que implementam uma interface
     * e recebem no construtor uma dependência de outro tipo.
     *
     * @return list<array<string, mixed>>
     */
    private function detectAdapter(): array
    {
        /** @var array<string, list<string>> class → interfaces it implements */
        $classInterfaces = [];

        foreach ($this->flow->edges() as $edge) {
            if ($edge->type() !== 'interface_implementation') {
                continue;
            }

            $class     = $this->extractClass($edge->from());
            $interface = $this->extractClass($edge->to());

            if ($class === null || $interface === null) {
                continue;
            }

            $classInterfaces[$class][] = $interface;
        }

        $found = [];
        foreach ($classInterfaces as $class => $interfaces) {
            $shortName = $this->shortName($class);

            $isAdapter = str_ends_with($shortName, 'Adapter')
                || str_ends_with($shortName, 'Gateway')
                || str_ends_with($shortName, 'Bridge')
                || str_ends_with($shortName, 'Wrapper');

            if (!$isAdapter) {
                continue;
            }

            $found[] = [
                'class'      => $class,
                'pattern'    => 'Adapter',
                'implements' => $interfaces,
                'confidence' => 'HIGH',
                'note'       => 'Adapts external dependency to internal interface(s): '
                    . implode(', ', array_map(fn($i) => $this->shortName($i), $interfaces)) . '.',
            ];
        }

        return $found;
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Agrupa métodos por classe a partir dos nodes do grafo.
     *
     * @return array<string, list<string>>
     */
    private function groupMethodsByClass(): array
    {
        $result = [];

        foreach ($this->flow->nodes() as $node) {
            $class  = $this->extractClass($node->id());
            $method = $this->extractMethod($node->id());

            if ($class === null || $method === null || str_starts_with($method, '__')) {
                continue;
            }

            $result[$class][] = $method;
        }

        return $result;
    }

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

    private function shortName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts);
    }

    /**
     * Verifica se existe uma interface correspondente (ex: *Repository → *RepositoryInterface).
     */
    private function hasMatchingInterface(string $class, string $suffix): bool
    {
        foreach ($this->flow->edges() as $edge) {
            if ($edge->type() !== 'interface_implementation') {
                continue;
            }

            if ($this->extractClass($edge->from()) !== $class) {
                continue;
            }

            $toClass = $this->extractClass($edge->to());
            if ($toClass !== null && str_contains($toClass, $suffix)) {
                return true;
            }
        }

        return false;
    }
}
