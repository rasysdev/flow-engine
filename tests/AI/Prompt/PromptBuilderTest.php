<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Prompt\PromptBuilder;
use FlowEngine\AI\Prompt\PromptTemplate;
use FlowEngine\AI\Prompt\SystemPrompt;

final class PromptBuilderTest extends TestCase
{
    public function test_build_includes_system_prompt(): void
    {
        $builder = new PromptBuilder();
        $tpl = new PromptTemplate('Test Title', 'Test body.');

        $result = $builder->build($tpl, ['key' => 'value']);

        $this->assertStringContainsString(SystemPrompt::text(), $result);
        $this->assertStringContainsString('## Test Title', $result);
        $this->assertStringContainsString('Test body.', $result);
        $this->assertStringContainsString('"key": "value"', $result);
    }

    public function test_build_user_prompt_excludes_system_prompt(): void
    {
        $builder = new PromptBuilder();
        $tpl = new PromptTemplate('Test Title', 'Test body.');

        $result = $builder->buildUserPrompt($tpl, ['key' => 'value']);

        $this->assertStringNotContainsString(SystemPrompt::text(), $result);
        $this->assertStringContainsString('## Test Title', $result);
        $this->assertStringContainsString('Test body.', $result);
        $this->assertStringContainsString('"key": "value"', $result);
    }

    public function test_build_user_prompt_contains_context_json(): void
    {
        $builder = new PromptBuilder();
        $tpl = new PromptTemplate('Title', 'Body');
        $context = ['cycles' => 3, 'nodes' => ['A', 'B']];

        $result = $builder->buildUserPrompt($tpl, $context);

        $this->assertStringContainsString('Context:', $result);
        $this->assertStringContainsString('"cycles": 3', $result);
    }
}
