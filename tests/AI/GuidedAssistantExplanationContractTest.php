<?php

namespace Tests\AI;

use FlowEngine\AI\Context\ContextAssembler;
use FlowEngine\AI\DTO\GuidedInputContext;
use FlowEngine\AI\NullGuidedAssistant;
use FlowEngine\Domain\Flow\Node;
use FlowEngine\Domain\Node\NodeVisibility;
use PHPUnit\Framework\TestCase;

final class GuidedAssistantExplanationContractTest extends TestCase
{
    public function test_null_assistant_has_no_suggestions_or_explanations(): void
    {
        $assistant = new NullGuidedAssistant();
        
        $node = $this->createTestNode();
        
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
        $this->assertEmpty($result->suggestions());
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