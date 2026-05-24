<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;
use FlowEngine\Domain\Contracts\FrameworkDetector;
use FlowEngine\Domain\Framework\Framework;

final class DefaultFrameworkDetector implements FrameworkDetector
{
    /** 
     * @internal
     */
    public function detect(ProjectContext $context): Framework
    {
        $root = $context->rootPath();

        if (file_exists($root . '/artisan')) {
            return Framework::laravel();
        }

        if (file_exists($root . '/wp-config.php')) {
            return Framework::wordpress();
        }

        if (file_exists($root . '/composer.json')) {
            return Framework::composer();
        }

        return Framework::custom();
    }
}
