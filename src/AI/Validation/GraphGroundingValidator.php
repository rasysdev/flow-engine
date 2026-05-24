<?php

namespace FlowEngine\AI\Validation;

use FlowEngine\Domain\Contracts\Flow;

final class GraphGroundingValidator
{
    /** @var array<string, true> */
    private array $nodeIds;

    private NodeReferenceExtractor $extractor;

    public function __construct(Flow $flow, ?NodeReferenceExtractor $extractor = null)
    {
        $ids = [];
        foreach ($flow->nodes() as $node) {
            $ids[$node->id()] = true;
        }

        $this->nodeIds = $ids;
        $this->extractor = $extractor ?? new NodeReferenceExtractor();
    }

    /**
     * Validates that references mentioned by the LLM exist in the actual graph.
     *
     * @return array<string, mixed>
     */
    public function validate(string $text): array
    {
        $refs = $this->extractor->extract($text);

        $known = [];
        $unknown = [];

        foreach ($refs as $ref) {
            if (isset($this->nodeIds[$ref])) {
                $known[] = $ref;
                continue;
            }

            $unknown[] = $ref;
        }

        return [
            'referencesFound' => $refs,
            'knownReferences' => $known,
            'unknownReferences' => $unknown,
            'ok' => count($unknown) === 0,
        ];
    }
}

