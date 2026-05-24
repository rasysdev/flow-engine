<?php

namespace Tests\Regression;

use FlowEngine\Application\Policy\DefaultNodeVisibilityPolicy;
use FlowEngine\Domain\Flow\DefaultNodeFactory;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Infrastructure\Analyzer\FlowBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Testes de regressão para refatoração do Core (Tasks 1.1-1.3).
 * 
 * Garante que funcionalidades existentes não foram quebradas:
 * - Criação de nodes via factory
 * - Aplicação de policies
 * - Construção de Flow
 * - Queries básicas
 */
final class CoreRefactoringRegressionTest extends TestCase
{
    /**
     * Cenário real: AstParser → NodeFactory → FlowBuilder → Flow
     */
    public function test_full_pipeline_from_factory_to_flow(): void
    {
        // 1. Criar nodes via factory (como AstParser faz)
        $factory = new DefaultNodeFactory();

        $nodes = [
            $factory->create('Calculator', 'sum', __FILE__, 10),
            $factory->create('Calculator', 'subtract', __FILE__, 20),
            $factory->create('Math', 'multiply', __FILE__, 30),
        ];

        // 2. Construir Flow via FlowBuilder (como Repository faz)
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build($nodes, []);

        // 3. Verificar que Flow está correto
        $this->assertCount(3, $flow->nodes());

        // 4. Verificar que todos os nodes passaram por policy
        foreach ($flow->nodes() as $node) {
            $this->assertTrue($node->hasEvaluatedVisibility());
            $this->assertTrue($node->isPublic()); // DefaultPolicy = PUBLIC
        }
    }

    /**
     * Cenário: Query nodes por classe
     */
    public function test_flow_query_still_works_after_refactoring(): void
    {
        $factory = new DefaultNodeFactory();

        $nodes = [
            $factory->create('Calculator', 'sum', __FILE__, 10),
            $factory->create('Calculator', 'subtract', __FILE__, 20),
            $factory->create('Math', 'multiply', __FILE__, 30),
        ];

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build($nodes, []);

        // Query por classe
        $calculatorNodes = $flow->query()
            ->byClass('Calculator')
            ->all();

        $this->assertCount(2, $calculatorNodes);
        $this->assertEquals('Calculator::sum', $calculatorNodes[0]->id());      // ← Corrigido
        $this->assertEquals('Calculator::subtract', $calculatorNodes[1]->id()); // ← Corrigido
    }

    /**
     * Cenário: Node individual acessível via ID
     */
    public function test_flow_node_lookup_still_works(): void
    {
        $factory = new DefaultNodeFactory();

        $node = $factory->create('Calculator', 'sum', __FILE__, 10);

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build([$node], []);

        $found = $flow->node('Calculator::sum');

        $this->assertNotNull($found);
        $this->assertEquals('Calculator', $found->class());
        $this->assertEquals('sum', $found->method());
    }

    /**
     * Cenário: Nodes criados diretamente (sem factory) ainda funcionam
     * 
     * IMPORTANTE: Não recomendado em produção, mas código legado pode fazer isso.
     */
    public function test_direct_node_creation_still_works(): void
    {
        // Criação direta (sem factory)
        $node = new Node('Calculator', 'sum', __FILE__, 10);

        $this->assertEquals('Calculator::sum', $node->id());
        $this->assertEquals('Calculator', $node->class());
        $this->assertEquals('sum', $node->method());

        // Não passou por policy ainda
        $this->assertFalse($node->hasEvaluatedVisibility());

        // Mas tem visibilidade default
        $this->assertTrue($node->isPublic());
    }

    /**
     * Cenário: withVisibility ainda funciona (imutabilidade)
     */
    public function test_node_immutability_preserved(): void
    {
        $factory = new DefaultNodeFactory();
        $original = $factory->create('Calculator', 'sum', __FILE__, 10);

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build([$original], []);

        $fromFlow = $flow->nodes()[0];

        // Original não foi mutado
        $this->assertFalse($original->hasEvaluatedVisibility());

        // Node do Flow é diferente (passou por policy)
        $this->assertTrue($fromFlow->hasEvaluatedVisibility());

        // Mas têm o mesmo ID
        $this->assertEquals($original->id(), $fromFlow->id());
    }

    /**
     * Cenário: Validação não quebra fluxo normal
     */
    public function test_validation_does_not_break_normal_flow(): void
    {
        $factory = new DefaultNodeFactory();

        // Criar 100 nodes válidos
        $nodes = [];
        for ($i = 1; $i <= 100; $i++) {
            $nodes[] = $factory->create(
                "Class{$i}",
                "method{$i}",
                __FILE__,
                $i
            );
        }

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());

        // Não deve lançar exceção
        $flow = $builder->build($nodes, []);

        $this->assertCount(100, $flow->nodes());
    }

    /**
     * Cenário: Flow vazio ainda funciona
     */
    public function test_empty_flow_still_works(): void
    {
        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build([], []);

        $this->assertCount(0, $flow->nodes());
        $this->assertCount(0, $flow->edges());

        // Query em flow vazio não quebra
        $result = $flow->query()->all();
        $this->assertCount(0, $result);
    }

    /**
     * Cenário: Node com line = null ainda funciona
     */
    public function test_node_with_null_line_works(): void
    {
        $factory = new DefaultNodeFactory();
        $node = $factory->create('Calculator', 'sum', __FILE__, null);

        $this->assertNull($node->line());

        $builder = new FlowBuilder(new DefaultNodeVisibilityPolicy());
        $flow = $builder->build([$node], []);

        $this->assertCount(1, $flow->nodes());
    }

    /**
     * Cenário: Diferentes policies ainda funcionam
     */
    public function test_different_policies_still_work(): void
    {
        $factory = new DefaultNodeFactory();
        $node = $factory->create('Calculator', 'sum', __FILE__, 10);

        // Policy que sempre retorna HIDDEN
        $hiddenPolicy = new class implements \FlowEngine\Application\Policy\NodeVisibilityPolicy {
            public function visibility(\FlowEngine\Domain\Flow\Node $node): \FlowEngine\Domain\Node\NodeVisibility
            {
                return new \FlowEngine\Domain\Node\NodeVisibility(\FlowEngine\Domain\Node\NodeVisibility::HIDDEN);
            }
        };

        $builder = new FlowBuilder($hiddenPolicy);
        $flow = $builder->build([$node], []);

        $nodeFromFlow = $flow->nodes()[0];

        $this->assertFalse($nodeFromFlow->isPublic());
        $this->assertTrue($nodeFromFlow->hasEvaluatedVisibility());
    }
}


