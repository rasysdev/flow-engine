<?php

namespace FlowEngine\Application\Policy;

use FlowEngine\Domain\Contracts\ProjectConfig;

final class ConfigurableNodeVisibilityPolicy extends CompositeNodeVisibilityPolicy
{
    /**
     * @param ProjectConfig $config Project configuration for visibility rules
     * @param NodeVisibilityPolicy[] $policies
     */
    public function __construct(
        private readonly ProjectConfig $config,
        array $policies
    ) {
        parent::__construct($policies);
    }

    /**
     * Returns the project configuration.
     *
     * @return ProjectConfig
     */
    public function config(): ProjectConfig
    {
        return $this->config;
    }
}
