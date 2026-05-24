<?php

namespace FlowEngine\Diagram;

class AsciiDiagramRenderer
{
    public function render(array $nodes, array $edges): string
    {
        if (empty($edges)) {
            return "No flow detected.";
        }

        $lines = [];

        foreach ($edges as $edge) {
            $from = $edge['from'] . '::' . $edge['method'];
            $to = $edge['to'] . '::' . $edge['method'];

            $lines[] = $from;
            $lines[] = str_repeat(' ', 8) . '|';
            $lines[] = str_repeat(' ', 8) . 'v';
            $lines[] = $to;
            $lines[] = ''; // espaço entre fluxos
        }

        return trim(implode("\n", $lines));
    }
}
