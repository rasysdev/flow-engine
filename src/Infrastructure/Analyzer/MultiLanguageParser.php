<?php

namespace FlowEngine\Infrastructure\Analyzer;

/**
 * Delegates parsing based on file extension.
 *
 * Supports compound extensions (e.g. "blade.php") checked before
 * single extensions (e.g. "php").
 */
final class MultiLanguageParser implements FileParser
{
    /** @var array<string, FileParser> */
    private array $parsersByExtension;

    /** @var array<string, FileParser> suffix => parser */
    private array $compoundParsers;

    /**
     * @param array<string, FileParser> $parsersByExtension
     * @param array<string, FileParser> $compoundParsers suffix => parser (e.g. 'blade.php' => BladeParser)
     */
    public function __construct(array $parsersByExtension, array $compoundParsers = [])
    {
        $normalized = [];
        foreach ($parsersByExtension as $ext => $parser) {
            $key = strtolower(ltrim((string) $ext, '.'));
            $normalized[$key] = $parser;
        }

        $this->parsersByExtension = $normalized;

        $normalizedCompound = [];
        foreach ($compoundParsers as $suffix => $parser) {
            $normalizedCompound[strtolower($suffix)] = $parser;
        }

        $this->compoundParsers = $normalizedCompound;
    }

    public function parse(string $file): array
    {
        $lower = strtolower($file);

        // Compound extension check first
        foreach ($this->compoundParsers as $suffix => $parser) {
            if (str_ends_with($lower, '.' . $suffix)) {
                return $parser->parse($file);
            }
        }

        // Fallback: single extension
        $ext = strtolower((string) pathinfo($file, PATHINFO_EXTENSION));

        $parser = $this->parsersByExtension[$ext] ?? null;
        if ($parser === null) {
            return ['nodes' => [], 'edges' => []];
        }

        return $parser->parse($file);
    }
}
