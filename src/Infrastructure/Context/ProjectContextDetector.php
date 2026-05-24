<?php

namespace FlowEngine\Infrastructure\Context;

use FlowEngine\Domain\Contracts\ProjectContext;

final class ProjectContextDetector
{
    /** 
     * @internal
     */
    public static function detect(string $projectPath): ProjectContext
    {
        if (file_exists($projectPath . '/artisan')) {
            return new LaravelProjectContext($projectPath);
        }

        if (file_exists($projectPath . '/yii')) {
            return new Yii2ProjectContext($projectPath);
        }

        if (file_exists($projectPath . '/wp-config.php')) {
            return new WordPressProjectContext($projectPath);
        }

        if (file_exists($projectPath . '/pubspec.yaml')) {
            return new FlutterProjectContext($projectPath);
        }

        if (file_exists($projectPath . '/vendor/autoload.php')) {
            return new ComposerProjectContext($projectPath);
        }

        return new GenericPhpProjectContext($projectPath);
    }
}
