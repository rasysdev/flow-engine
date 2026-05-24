<?php

namespace FlowEngine\AI\Validation;

final class NodeReferenceExtractor
{
    /**
     * Extracts potential node references from free-form text.
     *
     * Matches patterns like:
     * - App\\Service\\UserService::create
     * - UserService::create
     *
     * @return string[]
     */
    public function extract(string $text): array
    {
        $matches = [];

        preg_match_all(
            '/\b((?:[a-z]+:)?[A-Za-z_][A-Za-z0-9_\\\\.]*[A-Za-z0-9_])::([A-Za-z_][A-Za-z0-9_]*)\b/',
            $text,
            $matches
        );

        if (!isset($matches[0]) || !is_array($matches[0])) {
            return [];
        }

        return array_values(array_unique($matches[0]));
    }
}
