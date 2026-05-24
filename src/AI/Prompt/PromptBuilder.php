<?php
namespace FlowEngine\AI\Prompt;

final class PromptBuilder
{
    /** @param array<string,mixed> $context */
    public function build(PromptTemplate $tpl, array $context): string
    {
        return SystemPrompt::text()
            . "\n\n## {$tpl->title}\n"
            . $tpl->body
            . "\n\nContext:\n"
            . json_encode($context, JSON_PRETTY_PRINT);
    }

    /**
     * Builds a user prompt from a template and context (without system prompt).
     *
     * @param array<string,mixed> $context
     */
    public function buildUserPrompt(PromptTemplate $tpl, array $context): string
    {
        return "## {$tpl->title}\n"
            . $tpl->body
            . "\n\nContext:\n"
            . json_encode($context, JSON_PRETTY_PRINT);
    }
}
