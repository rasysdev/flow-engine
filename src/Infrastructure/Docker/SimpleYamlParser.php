<?php

namespace FlowEngine\Infrastructure\Docker;

final class SimpleYamlParser
{
    /**
     * @return array<string, mixed>
     */
    public function parseFile(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $content = (string) file_get_contents($path);
        return $this->parse($content);
    }

    /**
     * @return array<string, mixed>
     */
    public function parse(string $content): array
    {
        $lines = preg_split('/\r\n|\n|\r/', $content) ?: [];
        $index = 0;
        $parsed = $this->parseBlock($lines, $index, 0);

        return is_array($parsed) ? $parsed : [];
    }

    /**
     * @param string[] $lines
     * @return mixed
     */
    private function parseBlock(array $lines, int &$index, int $indent): mixed
    {
        $index = $this->skipEmpty($lines, $index);
        if (!isset($lines[$index])) {
            return [];
        }

        $currentIndent = $this->indent($lines[$index]);
        if ($currentIndent < $indent) {
            return [];
        }

        $trimmed = ltrim($this->stripComments($lines[$index]));
        if (str_starts_with($trimmed, '- ')) {
            return $this->parseSequence($lines, $index, $currentIndent);
        }

        return $this->parseMapping($lines, $index, $currentIndent);
    }

    /**
     * @param string[] $lines
     * @return array<int, mixed>
     */
    private function parseSequence(array $lines, int &$index, int $indent): array
    {
        $items = [];

        while (isset($lines[$index])) {
            $line = $this->stripComments($lines[$index]);
            if (trim($line) === '') {
                $index++;
                continue;
            }

            $lineIndent = $this->indent($line);
            if ($lineIndent < $indent) {
                break;
            }
            if ($lineIndent > $indent) {
                $index++;
                continue;
            }

            $trimmed = ltrim($line);
            if (!str_starts_with($trimmed, '- ')) {
                break;
            }

            $itemRest = trim(substr($trimmed, 2));
            $index++;

            if ($itemRest === '') {
                $items[] = $this->parseBlock($lines, $index, $indent + 2);
                continue;
            }

            if ($this->looksLikeMappingEntry($itemRest)) {
                [$key, $valuePart] = $this->splitKeyValue($itemRest);
                $item = [];
                $item[$key] = $valuePart === '' ? null : $this->parseScalar($valuePart);

                $nextIndex = $this->skipEmpty($lines, $index);
                if (isset($lines[$nextIndex]) && $this->indent($lines[$nextIndex]) > $indent) {
                    $nested = $this->parseBlock($lines, $index, $indent + 2);
                    if (is_array($nested)) {
                        if ($item[$key] === null && $this->isAssoc($nested)) {
                            $item[$key] = $nested;
                        } else {
                            foreach ($nested as $nestedKey => $nestedValue) {
                                $item[$nestedKey] = $nestedValue;
                            }
                        }
                    }
                }

                $items[] = $item;
                continue;
            }

            $items[] = $this->parseScalar($itemRest);
        }

        return $items;
    }

    /**
     * @param string[] $lines
     * @return array<string, mixed>
     */
    private function parseMapping(array $lines, int &$index, int $indent): array
    {
        $map = [];

        while (isset($lines[$index])) {
            $line = $this->stripComments($lines[$index]);
            if (trim($line) === '') {
                $index++;
                continue;
            }

            $lineIndent = $this->indent($line);
            if ($lineIndent < $indent) {
                break;
            }
            if ($lineIndent > $indent) {
                $index++;
                continue;
            }

            $trimmed = trim($line);
            if (!$this->looksLikeMappingEntry($trimmed)) {
                break;
            }

            [$key, $valuePart] = $this->splitKeyValue($trimmed);
            $index++;

            if ($valuePart === '') {
                $nextIndex = $this->skipEmpty($lines, $index);
                if (!isset($lines[$nextIndex]) || $this->indent($lines[$nextIndex]) <= $indent) {
                    $map[$key] = null;
                    continue;
                }

                $map[$key] = $this->parseBlock($lines, $index, $indent + 2);
                continue;
            }

            $map[$key] = $this->parseScalar($valuePart);
        }

        return $map;
    }

    private function looksLikeMappingEntry(string $line): bool
    {
        return preg_match('/^[^:\'"]+\s*:/', $line) === 1;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitKeyValue(string $line): array
    {
        $pos = strpos($line, ':');
        if ($pos === false) {
            return [trim($line), ''];
        }

        return [
            trim(substr($line, 0, $pos)),
            trim(substr($line, $pos + 1)),
        ];
    }

    private function parseScalar(string $value): mixed
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }

        if (($trimmed[0] === '"' && str_ends_with($trimmed, '"'))
            || ($trimmed[0] === "'" && str_ends_with($trimmed, "'"))
        ) {
            return substr($trimmed, 1, -1);
        }

        if ($trimmed === 'true') {
            return true;
        }
        if ($trimmed === 'false') {
            return false;
        }
        if ($trimmed === 'null' || $trimmed === '~') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $trimmed) === 1) {
            return (int) $trimmed;
        }
        if (preg_match('/^-?\d+\.\d+$/', $trimmed) === 1) {
            return (float) $trimmed;
        }

        if (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']')) {
            $inner = trim(substr($trimmed, 1, -1));
            if ($inner === '') {
                return [];
            }

            return array_map(
                fn(string $item): mixed => $this->parseScalar(trim($item)),
                preg_split('/\s*,\s*/', $inner) ?: []
            );
        }

        return $trimmed;
    }

    /**
     * @param string[] $lines
     */
    private function skipEmpty(array $lines, int $index): int
    {
        while (isset($lines[$index]) && trim($this->stripComments($lines[$index])) === '') {
            $index++;
        }

        return $index;
    }

    private function indent(string $line): int
    {
        preg_match('/^ */', $line, $matches);
        return strlen($matches[0] ?? '');
    }

    private function stripComments(string $line): string
    {
        $result = '';
        $quote = null;
        $len = strlen($line);

        for ($i = 0; $i < $len; $i++) {
            $char = $line[$i];

            if (($char === '"' || $char === "'")) {
                if ($quote === null) {
                    $quote = $char;
                } elseif ($quote === $char) {
                    $quote = null;
                }
            }

            if ($char === '#' && $quote === null) {
                break;
            }

            $result .= $char;
        }

        return rtrim($result);
    }

    /**
     * @param array<mixed> $value
     */
    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }
}
