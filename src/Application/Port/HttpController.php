<?php

namespace FlowEngine\Application\Port;

interface HttpController
{
    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handle(array $request): array;
}
