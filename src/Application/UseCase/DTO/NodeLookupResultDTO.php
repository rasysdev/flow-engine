<?php

namespace FlowEngine\Application\UseCase\DTO;

final readonly class NodeLookupResultDTO
{
    /**
     * @param string $query Original search query
     * @param array<int, array{type: string, id: string, file?: string, methods?: string[], fan_in: int, fan_out: int}> $matches
     */
    public function __construct(
        public string $query,
        public array $matches
    ) {
    }

    public function toJson(): string
    {
        return json_encode(
            ['query' => $this->query, 'matches' => $this->matches],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
        );
    }
}
