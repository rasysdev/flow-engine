<?php

namespace FlowEngine\Application\DTO;

/**
 * DTO readonly representando uma aresta (edge) no grafo.
 * 
 * Representa uma chamada de método (from → to).
 */
final readonly class EdgeDTO
{
    public function __construct(
        public string $from,
        public string $to,
        public string $type = 'method_call',
        public string $method = ''
    ) {
    }

    /**
     * Cria DTO a partir de entidade de Domain.
     *
     * @param \FlowEngine\Domain\Flow\Edge $edge
     * @return self
     */
    public static function fromEdge(\FlowEngine\Domain\Flow\Edge $edge): self
    {
        return new self(
            from: $edge->from(),
            to: $edge->to(),
            type: $edge->type(),
            method: $edge->method()
        );
    }

    /**
     * Serializa para array.
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'type' => $this->type,
            'method' => $this->method,
        ];
    }

    /**
     * Serializa para JSON.
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR);
    }
}