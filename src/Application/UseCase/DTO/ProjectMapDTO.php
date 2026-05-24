<?php

namespace FlowEngine\Application\UseCase\DTO;

final readonly class ProjectMapDTO
{
    /**
     * @param string $project Absolute path to the project root
     * @param string $language Detected primary language
     * @param string|null $framework Detected framework or null
     * @param array{nodes: int, edges: int, cycles?: int} $stats Graph statistics
     * @param array<int, array{namespace: string, classes: int}> $top_namespaces Top namespaces by class count
     * @param array<int, string> $entrypoints Top entrypoint node IDs
     * @param array<int, array{id: string, fan_in: int}> $hotspots_top5 Top 5 hotspots by fan_in
     */
    public function __construct(
        public string $project,
        public string $language,
        public ?string $framework,
        public array $stats,
        public array $top_namespaces,
        public array $entrypoints,
        public array $hotspots_top5
    ) {
    }

    public function toJson(): string
    {
        $data = [
            'project'        => $this->project,
            'language'       => $this->language,
            'framework'      => $this->framework,
            'stats'          => $this->stats,
            'top_namespaces' => $this->top_namespaces,
            'entrypoints'    => $this->entrypoints,
            'hotspots_top5'  => $this->hotspots_top5,
        ];

        $flags = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;
        $json  = json_encode($data, $flags);

        if (strlen($json) > 1500) {
            $data['entrypoints']    = array_slice($this->entrypoints, 0, 10);
            $data['top_namespaces'] = array_slice($this->top_namespaces, 0, 3);
            $json = json_encode($data, $flags);
        }

        return $json;
    }
}
