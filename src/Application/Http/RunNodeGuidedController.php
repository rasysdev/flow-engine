<?php

namespace FlowEngine\Application\Http;

use FlowEngine\Application\Port\HttpController;
use FlowEngine\Application\UseCase\RunNodeGuided;
use LogicException;

final class RunNodeGuidedController implements HttpController
{
    public function __construct(
        private RunNodeGuided $useCase
    ) {
    }

    public function handle(array $request): array
    {
        try {
            $result = $this->useCase->execute(
                $request['nodeId'],
                $request['args'] ?? []
            );

            return [
                'status' => 200,
                'data' => $result,
            ];
        } catch (LogicException $e) {
            return [
                'status' => 400,
                'error' => $e->getMessage(),
            ];
        }
    }
}
