<?php

namespace FlowEngine\Infrastructure\Analyzer\Visitors;

use PhpParser\Node;

/**
 * Detecta componentes Laravel e cria nó sintético ClassName::__laravel.
 *
 * Classificação baseada em:
 * - Herança (extends Controller, Model, ServiceProvider, etc.)
 * - Interfaces implementadas (ShouldQueue → job, ShouldBroadcast → event)
 * - Sufixo do nome da classe (Controller, Job, Event, Listener, etc.)
 *
 * O nó sintético `ClassName::__laravel` carrega metadata:
 *   {laravel_type: 'controller'|'model'|'job'|'event'|'listener'|...}
 *
 * @internal
 */
final class LaravelClassifierVisitor implements NodeVisitor
{
    private ?string $detectedType = null;

    private const PARENT_MAP = [
        'Controller'      => 'controller',
        'BaseController'  => 'controller',
        'Model'           => 'model',
        'Authenticatable' => 'model',
        'ServiceProvider' => 'service_provider',
        'FormRequest'     => 'form_request',
        'Request'         => 'form_request',
        'Rule'            => 'validation_rule',
        'Mailable'        => 'mailable',
        'Notification'    => 'notification',
        'Resource'        => 'api_resource',
        'JsonResource'    => 'api_resource',
        'ResourceCollection' => 'api_resource',
        'Seeder'          => 'seeder',
        'Migration'       => 'migration',
        'Cast'            => 'cast',
        'Policy'          => 'policy',
    ];

    private const INTERFACE_MAP = [
        'ShouldQueue'       => 'job',
        'ShouldBroadcast'   => 'event',
        'ShouldBroadcastNow' => 'event',
    ];

    private const SUFFIX_MAP = [
        'Controller'      => 'controller',
        'Job'             => 'job',
        'Event'           => 'event',
        'Listener'        => 'listener',
        'Observer'        => 'observer',
        'Policy'          => 'policy',
        'Middleware'       => 'middleware',
        'Repository'      => 'repository',
        'ServiceProvider' => 'service_provider',
        'Request'         => 'form_request',
        'Resource'        => 'api_resource',
        'Factory'         => 'factory',
        'Seeder'          => 'seeder',
        'Rule'            => 'validation_rule',
        'Mailable'        => 'mailable',
        'Notification'    => 'notification',
        'Observer'        => 'observer',
        'Scope'           => 'scope',
        'Cast'            => 'cast',
        'Command'         => 'artisan_command',
    ];

    /**
     * @internal
     */
    public function supports(Node $node): bool
    {
        return $node instanceof Node\Stmt\Class_;
    }

    /**
     * @internal
     */
    public function enter(Node $node, VisitorContext $context): void
    {
        $this->detectedType = null;

        /** @var Node\Stmt\Class_ $node */
        $className = $node->name?->toString();
        if (!$className) {
            return;
        }

        $this->detectedType = $this->classify($node, $className);
    }

    /**
     * @internal
     */
    public function leave(Node $node, VisitorContext $context): void
    {
        if ($this->detectedType === null) {
            return;
        }

        $class = $context->currentClass();
        if ($class === null) {
            $this->detectedType = null;
            return;
        }

        $laravelNode = $context->nodeFactory()->create(
            $class,
            '__laravel',
            $context->file(),
            $node->getStartLine(),
            'php',
            ['laravel_type' => $this->detectedType]
        );

        $context->addNode($laravelNode);
        $this->detectedType = null;
    }

    /**
     * Determina o tipo Laravel do componente.
     */
    private function classify(Node\Stmt\Class_ $node, string $className): ?string
    {
        // 1. Por classe pai (extends)
        if ($node->extends !== null) {
            $parentShort = $node->extends->getLast();
            if (isset(self::PARENT_MAP[$parentShort])) {
                return self::PARENT_MAP[$parentShort];
            }
            // Modelos Eloquent que herdam de classes personalizadas (ex: BaseModel)
            if (str_ends_with($parentShort, 'Model')) {
                return 'model';
            }
        }

        // 2. Por interfaces implementadas
        foreach ($node->implements as $interface) {
            $ifaceShort = $interface->getLast();
            if (isset(self::INTERFACE_MAP[$ifaceShort])) {
                return self::INTERFACE_MAP[$ifaceShort];
            }
        }

        // 3. Por sufixo do nome da classe (mais genérico)
        foreach (self::SUFFIX_MAP as $suffix => $type) {
            if (str_ends_with($className, $suffix)) {
                return $type;
            }
        }

        return null;
    }
}
