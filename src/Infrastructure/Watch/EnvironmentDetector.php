<?php

namespace FlowEngine\Infrastructure\Watch;

final class EnvironmentDetector
{
    /**
     * @param callable(string):bool $fileExists
     * @param callable(string):string|false $readFile
     */
    public function __construct(
        private $fileExists,
        private $readFile
    ) {
    }

    public static function createDefault(): self
    {
        return new self(
            fn(string $path): bool => file_exists($path),
            fn(string $path) => file_get_contents($path)
        );
    }

    public function isDocker(): bool
    {
        if (($this->fileExists)('/.dockerenv')) {
            return true;
        }

        if (($this->fileExists)('/proc/1/cgroup')) {
            $content = (string) ($this->readFile)('/proc/1/cgroup');
            if (str_contains($content, 'docker') || str_contains($content, 'kubepods')) {
                return true;
            }
        }

        return false;
    }
}
