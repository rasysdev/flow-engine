<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\InterpretViolations;
use FlowEngine\Application\UseCase\AnalyzeArchitecture;
use FlowEngine\AI\Context\ContextAssembler;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMResponse;
use FlowEngine\AI\LLM\NullLLMProvider;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\Application\DTO\InterpretationResultDTO;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;

final class InterpretViolationsTest extends TestCase
{
    public function test_early_return_when_clean_architecture(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new InterpretViolations(
            new AnalyzeArchitecture($repo),
            new NullLLMProvider(),
            new ContextAssembler(),
            new PromptBuilder(),
            $repo->getFlow()
        );

        $result = $useCase->execute();

        $this->assertSame('violations', $result->type);
        $this->assertStringContainsString('No architecture violations', $result->interpretation);
        $this->assertSame(0, $result->tokensUsed);
    }

    public function test_result_is_interpretation_result_dto(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new InterpretViolations(
            new AnalyzeArchitecture($repo),
            new NullLLMProvider(),
            new ContextAssembler(),
            new PromptBuilder(),
            $repo->getFlow()
        );

        $result = $useCase->execute();

        $this->assertInstanceOf(InterpretationResultDTO::class, $result);
    }

    public function test_result_serializes_to_json(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new InterpretViolations(
            new AnalyzeArchitecture($repo),
            new NullLLMProvider(),
            new ContextAssembler(),
            new PromptBuilder(),
            $repo->getFlow()
        );

        $result = $useCase->execute();
        $json = $result->toJson();

        $this->assertJson($json);
    }

    public function test_context_contains_report_data(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new InterpretViolations(
            new AnalyzeArchitecture($repo),
            new NullLLMProvider(),
            new ContextAssembler(),
            new PromptBuilder(),
            $repo->getFlow()
        );

        $result = $useCase->execute();
        $array = $result->toArray();

        $this->assertArrayHasKey('context', $array);
    }
}
