<?php

namespace FlowEngine\Infrastructure\Config;

use FlowEngine\Domain\Contracts\ProjectConfig;

final class ProjectConfigLoader
{

    /**
     * @internal
     * @param string $rootPath
     * @return FileProjectConfig
     */
    public static function load(string $rootPath): ProjectConfig
    {
        $file = $rootPath . DIRECTORY_SEPARATOR . 'flow-engine.json';

        if (!file_exists($file)) {
            return new FileProjectConfig([], $rootPath);
        }

        $data = json_decode(
            file_get_contents($file),
            true,
            flags: JSON_THROW_ON_ERROR
        );

        return new FileProjectConfig($data, $rootPath);
    }
}
