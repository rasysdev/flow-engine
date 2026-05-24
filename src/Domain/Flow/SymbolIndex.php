<?php

declare(strict_types=1);

namespace FlowEngine\Domain\Flow;

use FlowEngine\Application\DTO\SymbolDTO;

/**
 * Domain collection of SymbolDTOs, indexed for fast substring lookup by name.
 *
 * @api
 */
final class SymbolIndex
{
    /** @var SymbolDTO[] */
    private array $symbols;

    /**
     * @param SymbolDTO[] $symbols
     */
    public function __construct(array $symbols = [])
    {
        $this->symbols = array_values($symbols);
    }

    /**
     * Find symbols whose name contains the given substring (case-insensitive).
     *
     * @return SymbolDTO[]
     */
    public function findByName(string $substring, int $limit = 20): array
    {
        $needle  = strtolower(trim($substring));
        $matches = [];

        foreach ($this->symbols as $symbol) {
            if ($needle === '' || str_contains(strtolower($symbol->name), $needle)) {
                $matches[] = $symbol;
                if (count($matches) >= $limit) {
                    break;
                }
            }
        }

        return $matches;
    }

    /**
     * Find symbols that exactly match the given name (case-insensitive).
     *
     * @return SymbolDTO[]
     */
    public function findExact(string $name): array
    {
        $needle  = strtolower($name);
        $matches = [];

        foreach ($this->symbols as $symbol) {
            if (strtolower($symbol->name) === $needle) {
                $matches[] = $symbol;
            }
        }

        return $matches;
    }

    /**
     * @return SymbolDTO[]
     */
    public function all(): array
    {
        return $this->symbols;
    }

    public function count(): int
    {
        return count($this->symbols);
    }

    /**
     * @return array<string, mixed>[]
     */
    public function toArray(): array
    {
        return array_map(fn(SymbolDTO $s) => $s->toArray(), $this->symbols);
    }
}
