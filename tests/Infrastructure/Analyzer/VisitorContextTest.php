<?php

namespace Tests\Infrastructure\Analyzer;

use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Domain\Flow\Edge;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Infrastructure\Analyzer\Visitors\VisitorContext;
use PHPUnit\Framework\TestCase;

/**
 * Testes para VisitorContext.
 * 
 * Valida gerenciamento de estado compartilhado entre visitors.
 */
final class VisitorContextTest extends TestCase
{
    private VisitorContext $context;
    private DefaultNodeFactory $factory;
    private string $testFile;

    protected function setUp(): void
    {
        $this->factory = new DefaultNodeFactory();
        
        // Criar arquivo temporário real
        $this->testFile = sys_get_temp_dir() . '/TestClass-' . uniqid() . '.php';
        file_put_contents($this->testFile, '<?php class TestClass {}');
        
        $this->context = new VisitorContext($this->testFile, $this->factory);
    }

    protected function tearDown(): void
    {
        // Limpar arquivo temporário
        if (file_exists($this->testFile)) {
            unlink($this->testFile);
        }
    }

    // ==========================================
    // State Management Tests
    // ==========================================

    public function test_initial_state_is_clean(): void
    {
        $this->assertNull($this->context->currentNamespace());
        $this->assertNull($this->context->currentClass());
        $this->assertNull($this->context->currentMethod());
        $this->assertEmpty($this->context->currentTraits());
        $this->assertEmpty($this->context->getNodes());
        $this->assertEmpty($this->context->getEdges());
    }

    public function test_set_and_get_namespace(): void
    {
        $this->context->setNamespace('App\Services');

        $this->assertEquals('App\Services', $this->context->currentNamespace());
    }

    public function test_set_and_get_class(): void
    {
        $this->context->setClass('App\Services\UserService');

        $this->assertEquals('App\Services\UserService', $this->context->currentClass());
    }

    public function test_set_class_clears_traits(): void
    {
        // Adicionar traits
        $this->context->addTrait('LoggerTrait');
        $this->assertCount(1, $this->context->currentTraits());

        // Mudar de classe deve limpar traits
        $this->context->setClass('AnotherClass');

        $this->assertEmpty($this->context->currentTraits());
    }

    public function test_set_and_get_method(): void
    {
        $this->context->setMethod('create');

        $this->assertEquals('create', $this->context->currentMethod());
    }

    public function test_add_and_get_traits(): void
    {
        $this->context->addTrait('LoggerTrait');
        $this->context->addTrait('TimestampTrait');

        $traits = $this->context->currentTraits();

        $this->assertCount(2, $traits);
        $this->assertContains('LoggerTrait', $traits);
        $this->assertContains('TimestampTrait', $traits);
    }

    public function test_file_is_preserved(): void
    {
        $this->assertEquals($this->testFile, $this->context->file());
    }

    // ==========================================
    // Node & Edge Collection Tests
    // ==========================================

    public function test_add_and_get_nodes(): void
    {
        $node1 = $this->factory->create('UserService', 'create', $this->testFile, 10);
        $node2 = $this->factory->create('UserService', 'update', $this->testFile, 20);

        $this->context->addNode($node1);
        $this->context->addNode($node2);

        $nodes = $this->context->getNodes();

        $this->assertCount(2, $nodes);
        $this->assertSame($node1, $nodes[0]);
        $this->assertSame($node2, $nodes[1]);
    }

    public function test_add_and_get_edges(): void
    {
        $edge1 = new Edge('UserService::create', 'UserRepository::save', 'save', 'method_call');
        $edge2 = new Edge('UserService::update', 'UserRepository::update', 'update', 'method_call');

        $this->context->addEdge($edge1);
        $this->context->addEdge($edge2);

        $edges = $this->context->getEdges();

        $this->assertCount(2, $edges);
        $this->assertSame($edge1, $edges[0]);
        $this->assertSame($edge2, $edges[1]);
    }

    // ==========================================
    // Helper Methods Tests
    // ==========================================

    public function test_current_node_id_returns_null_when_incomplete(): void
    {
        // Sem classe
        $this->assertNull($this->context->currentNodeId());

        // Só classe
        $this->context->setClass('UserService');
        $this->assertNull($this->context->currentNodeId());

        // Só método
        $this->context->setClass(null);
        $this->context->setMethod('create');
        $this->assertNull($this->context->currentNodeId());
    }

    public function test_current_node_id_returns_id_when_complete(): void
    {
        $this->context->setClass('UserService');
        $this->context->setMethod('create');

        $this->assertEquals('UserService::create', $this->context->currentNodeId());
    }

    public function test_is_inside_method_returns_false_when_incomplete(): void
    {
        $this->assertFalse($this->context->isInsideMethod());

        $this->context->setClass('UserService');
        $this->assertFalse($this->context->isInsideMethod());

        $this->context->setClass(null);
        $this->context->setMethod('create');
        $this->assertFalse($this->context->isInsideMethod());
    }

    public function test_is_inside_method_returns_true_when_complete(): void
    {
        $this->context->setClass('UserService');
        $this->context->setMethod('create');

        $this->assertTrue($this->context->isInsideMethod());
    }

    public function test_is_inside_class_returns_false_when_no_class(): void
    {
        $this->assertFalse($this->context->isInsideClass());
    }

    public function test_is_inside_class_returns_true_when_class_set(): void
    {
        $this->context->setClass('UserService');

        $this->assertTrue($this->context->isInsideClass());
    }

    // ==========================================
    // FQN Resolution Tests
    // ==========================================

    public function test_resolve_fqn_returns_as_is_when_already_fqn(): void
    {
        $this->context->setNamespace('App\Services');

        $result = $this->context->resolveFQN('App\Models\User');

        $this->assertEquals('App\Models\User', $result);
    }

    public function test_resolve_fqn_adds_namespace_when_relative(): void
    {
        $this->context->setNamespace('App\Services');

        $result = $this->context->resolveFQN('UserService');

        $this->assertEquals('App\Services\UserService', $result);
    }

    public function test_resolve_fqn_returns_as_is_when_no_namespace(): void
    {
        $this->context->setNamespace(null);

        $result = $this->context->resolveFQN('GlobalClass');

        $this->assertEquals('GlobalClass', $result);
    }

    public function test_resolve_fqn_handles_leading_backslash(): void
    {
        $this->context->setNamespace('App\Services');

        $result = $this->context->resolveFQN('\stdClass');

        // Leading backslash indica classe global
        $this->assertEquals('\stdClass', $result);
    }

    // ==========================================
    // NodeFactory Access Tests
    // ==========================================

    public function test_node_factory_is_accessible(): void
    {
        $factory = $this->context->nodeFactory();

        $this->assertSame($this->factory, $factory);
    }

    public function test_can_create_nodes_via_factory(): void
    {
        $factory = $this->context->nodeFactory();
        $node = $factory->create('TestClass', 'testMethod', $this->testFile, 42);

        $this->assertInstanceOf(Node::class, $node);
        $this->assertEquals('TestClass::testMethod', $node->id());
    }

    // ==========================================
    // Integration Tests
    // ==========================================

    public function test_typical_visitor_workflow(): void
    {
        // Simular workflow de visitors

        // 1. NamespaceVisitor
        $this->context->setNamespace('App\Services');

        // 2. ClassVisitor
        $this->context->setClass($this->context->resolveFQN('UserService'));
        $this->context->addTrait('LoggerTrait');

        // 3. MethodVisitor
        $this->context->setMethod('create');
        $node = $this->context->nodeFactory()->create(
            $this->context->currentClass(),
            $this->context->currentMethod(),
            $this->context->file(),
            10
        );
        $this->context->addNode($node);

        // 4. MethodCallVisitor
        $edge = new Edge(
            $this->context->currentNodeId(),
            'App\Repository\UserRepository::save',
            'save',
            'method_call'
        );
        $this->context->addEdge($edge);

        // Verificações
        $this->assertEquals('App\Services', $this->context->currentNamespace());
        $this->assertEquals('App\Services\UserService', $this->context->currentClass());
        $this->assertEquals('create', $this->context->currentMethod());
        $this->assertCount(1, $this->context->currentTraits());
        $this->assertCount(1, $this->context->getNodes());
        $this->assertCount(1, $this->context->getEdges());
    }

    public function test_context_reset_between_classes(): void
    {
        // Primeira classe
        $this->context->setNamespace('App\Services');
        $this->context->setClass('UserService');
        $this->context->addTrait('LoggerTrait');
        $this->context->setMethod('create');

        // Mudar para segunda classe
        $this->context->setClass('ProductService');

        // Traits devem ser limpas
        $this->assertEmpty($this->context->currentTraits());
        
        // Namespace e classe devem estar atualizados
        $this->assertEquals('App\Services', $this->context->currentNamespace());
        $this->assertEquals('ProductService', $this->context->currentClass());
        
        // Método deve permanecer (até ser explicitamente limpo)
        $this->assertEquals('create', $this->context->currentMethod());
    }

    public function test_multiple_nodes_and_edges_collection(): void
    {
        $this->context->setNamespace('App');
        $this->context->setClass('App\TestClass');

        // Adicionar múltiplos nodes
        for ($i = 1; $i <= 5; $i++) {
            $node = $this->factory->create(
                'App\TestClass',
                'method' . $i,
                $this->testFile,
                $i * 10
            );
            $this->context->addNode($node);
        }

        // Adicionar múltiplas edges
        for ($i = 1; $i <= 3; $i++) {
            $edge = new Edge(
                'App\TestClass::method1',
                'App\Helper::helper' . $i,
                'helper' . $i,
                'method_call'
            );
            $this->context->addEdge($edge);
        }

        $this->assertCount(5, $this->context->getNodes());
        $this->assertCount(3, $this->context->getEdges());
    }
}