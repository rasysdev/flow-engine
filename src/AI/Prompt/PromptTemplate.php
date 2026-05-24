<?php
namespace FlowEngine\AI\Prompt;

final class PromptTemplate
{
    public function __construct(
        public readonly string $title,
        public readonly string $body
    ) {
    }
}
