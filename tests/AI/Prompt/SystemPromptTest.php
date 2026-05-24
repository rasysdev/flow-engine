<?php
use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Prompt\SystemPrompt;

final class SystemPromptTest extends TestCase
{
    public function test_prompt_has_no_imperatives(): void
    {
        $text = SystemPrompt::text();
        $this->assertStringNotContainsString('execute', strtolower($text));
        $this->assertStringNotContainsString('change', strtolower($text));
    }
}
