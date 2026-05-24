<?php

namespace FlowEngine\Infrastructure\Analyzer;

interface FileParser
{
    /**
     * @param string $file Absolute file path
     * @return array{nodes: array, edges: array}
     */
    public function parse(string $file): array;
}

