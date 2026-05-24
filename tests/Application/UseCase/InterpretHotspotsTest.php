<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\Application\UseCase\InterpretHotspots;
use FlowEngine\Application\UseCase\AnalyzeComplexity;
use FlowEngine\AI\Context\ContextAssembler;
use FlowEngine\AI\LLM\LLMProvider;
use FlowEngine\AI\LLM\LLMResponse;
use FlowEngine\AI\LLM\NullLLMProvider;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\Application\DTO\InterpretationResultDTO;
use FlowEngine\Domain\Flow\Node;
use Tests\Support\InMemoryFlowRepository;

final class InterpretHotspotsTest extends TestCase
{
    public function test_early_return_when_no_hotspots(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new InterpretHotspots(
            new AnalyzeComplexity($repo),
            new NullLLMProvider(),
            new ContextAssembler(),
            new PromptBuilder(),
            $repo->getFlow()
        );

        $result = $useCase->execute();

        $this->assertSame('hotspots', $result->type);
        $this->assertSame('No complexity hotspots detected.', $result->interpretation);
        $this->assertSame(0, $result->tokensUsed);
    }

    public function test_result_is_interpretation_result_dto(): void
    {
        $repo = new InMemoryFlowRepository([
            new Node('App\\Service', 'handle', __FILE__, 1),
        ]);

        $useCase = new InterpretHotspots(
            new AnalyzeComplexity($repo),
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

        $useCase = new InterpretHotspots(
            new AnalyzeComplexity($repo),
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

        $useCase = new InterpretHotspots(
            new AnalyzeComplexity($repo),
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
