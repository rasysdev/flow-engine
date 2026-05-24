<?php

use PHPUnit\Framework\TestCase;
use FlowEngine\AI\Prompt\InterpretationPrompts;
use FlowEngine\AI\Prompt\PromptTemplate;

final class InterpretationPromptsTest extends TestCase
{
    public function test_cycles_returns_prompt_template(): void
    {
        $tpl = InterpretationPrompts::cycles();

        $this->assertInstanceOf(PromptTemplate::class, $tpl);
        $this->assertStringContainsString('Cycle', $tpl->title);
        $this->assertNotEmpty($tpl->body);
    }

    public function test_hotspots_returns_prompt_template(): void
    {
        $tpl = InterpretationPrompts::hotspots();

        $this->assertInstanceOf(PromptTemplate::class, $tpl);
        $this->assertStringContainsString('Hotspot', $tpl->title);
        $this->assertNotEmpty($tpl->body);
    }

    public function test_impact_returns_prompt_template(): void
    {
        $tpl = InterpretationPrompts::impact();

        $this->assertInstanceOf(PromptTemplate::class, $tpl);
        $this->assertStringContainsString('Impact', $tpl->title);
        $this->assertNotEmpty($tpl->body);
    }

    public function test_violations_returns_prompt_template(): void
    {
        $tpl = InterpretationPrompts::violations();

        $this->assertInstanceOf(PromptTemplate::class, $tpl);
        $this->assertStringContainsString('Violation', $tpl->title);
        $this->assertNotEmpty($tpl->body);
    }

    public function test_all_returns_eight_entries(): void
    {
        $all = InterpretationPrompts::all();

        $this->assertCount(8, $all);
        $this->assertArrayHasKey('cycles', $all);
        $this->assertArrayHasKey('hotspots', $all);
        $this->assertArrayHasKey('impact', $all);
        $this->assertArrayHasKey('violations', $all);
        $this->assertArrayHasKey('changeImpact', $all);
        $this->assertArrayHasKey('refactorPlan', $all);
        $this->assertArrayHasKey('refactorGuidance', $all);
        $this->assertArrayHasKey('pullRequest', $all);
    }

    public function test_prompts_do_not_contain_imperative_actions(): void
    {
        $templates = [
            InterpretationPrompts::cycles(),
            InterpretationPrompts::hotspots(),
            InterpretationPrompts::impact(),
            InterpretationPrompts::violations(),
        ];

        foreach ($templates as $tpl) {
            $this->assertStringNotContainsString('execute', strtolower($tpl->body));
            $this->assertStringNotContainsString('delete', strtolower($tpl->body));
            $this->assertStringNotContainsString('modify', strtolower($tpl->body));
        }
    }
}
