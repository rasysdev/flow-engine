<?php

use FlowEngine\AI\Context\ContextAssembler;
use FlowEngine\AI\DTO\GuidedInputContext;
use FlowEngine\AI\NullGuidedAssistant;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;
use PHPUnit\Framework\TestCase;

final class GuidedAssistantContractTest extends TestCase
{
    public function test_null_assistant_never_suggests_inputs(): void
    {
        $assistant = new NullGuidedAssistant();
        
        // Criar Node real (é final, não pode mockar)
        $node = $this->createTestNode();
        
        // Usar ContextAssembler para converter
        $assembler = new ContextAssembler();
        $context = new GuidedInputContext(
            node: $assembler->node(
                $node->id(),
                $node->class(),
                $node->method(),
                $node->visibility()->value()
            ),
            inputs: [],
            visibility: [],
            impact: []
        );
        
        $result = $assistant->suggestInputs($context);
        
        $this->assertTrue($result->isEmpty());
    }
    
    private function createTestNode(): Node
    {
        return new Node(
            class: 'TestClass',
            method: 'testMethod',
            file: __FILE__,
            line: 1
        );
    }
}