<?php

namespace Tests\Infrastructure\Paths;

use FlowEngine\Infrastructure\Paths\StateDirectory;
use PHPUnit\Framework\TestCase;

final class StateDirectoryTest extends TestCase
{
    private string $originalStateDirEnv;
    private string $originalHomeEnv;

    protected function setUp(): void
    {
        $this->originalStateDirEnv = getenv('FLOW_ENGINE_STATE_DIR') ?: '';
        $this->originalHomeEnv     = getenv('HOME') ?: '';
    }

    protected function tearDown(): void
    {
        if ($this->originalStateDirEnv === '') {
            putenv('FLOW_ENGINE_STATE_DIR');
        } else {
            putenv('FLOW_ENGINE_STATE_DIR=' . $this->originalStateDirEnv);
        }

        if ($this->originalHomeEnv === '') {
            putenv('HOME');
        } else {
            putenv('HOME=' . $this->originalHomeEnv);
        }
    }

    // -------------------------------------------------------------------------

    public function test_default_path_uses_home_cache_directory(): void
    {
        putenv('FLOW_ENGINE_STATE_DIR');
        putenv('HOME=/home/fake-user');

        $root      = sys_get_temp_dir();
        $clean     = rtrim($root, DIRECTORY_SEPARATOR);
        $canonical = realpath($clean) ?: $clean;
        $projectId = sha1($canonical);
        $expected  = '/home/fake-user' . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR
            . 'flow-engine' . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . '.flow-engine';

        $this->assertSame($expected, StateDirectory::forProjectRoot($root));
    }

    public function test_env_override_uses_hashed_subdir(): void
    {
        $overrideDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ci-cache';
        putenv('FLOW_ENGINE_STATE_DIR=' . $overrideDir);

        $root      = sys_get_temp_dir();
        $clean     = rtrim($root, DIRECTORY_SEPARATOR);
        $canonical = realpath($clean) ?: $clean;
        $projectId = sha1($canonical);
        $expected  = rtrim($overrideDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $projectId
            . DIRECTORY_SEPARATOR . '.flow-engine';

        $this->assertSame($expected, StateDirectory::forProjectRoot($root));
    }

    public function test_empty_env_falls_back_to_home_cache(): void
    {
        putenv('FLOW_ENGINE_STATE_DIR=');
        putenv('HOME=/home/fake-user');

        $root      = sys_get_temp_dir();
        $clean     = rtrim($root, DIRECTORY_SEPARATOR);
        $canonical = realpath($clean) ?: $clean;
        $projectId = sha1($canonical);
        $expected  = '/home/fake-user' . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR
            . 'flow-engine' . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . '.flow-engine';

        $this->assertSame($expected, StateDirectory::forProjectRoot($root));
    }

    public function test_no_home_falls_back_to_system_temp(): void
    {
        putenv('FLOW_ENGINE_STATE_DIR');
        putenv('HOME');

        $root      = sys_get_temp_dir();
        $clean     = rtrim($root, DIRECTORY_SEPARATOR);
        $canonical = realpath($clean) ?: $clean;
        $projectId = sha1($canonical);
        $expected  = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'flow-engine' . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . '.flow-engine';

        $this->assertSame($expected, StateDirectory::forProjectRoot($root));
    }

    public function test_trailing_separator_and_realpath_canonicalization(): void
    {
        putenv('FLOW_ENGINE_STATE_DIR');
        putenv('HOME=/home/fake-user');

        $root = sys_get_temp_dir();

        $withTrailing = StateDirectory::forProjectRoot($root . DIRECTORY_SEPARATOR);
        $withoutTrailing = StateDirectory::forProjectRoot($root);

        $this->assertSame($withoutTrailing, $withTrailing);
    }

    public function test_env_override_trailing_separator_stripped(): void
    {
        $overrideDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ci-cache';
        putenv('FLOW_ENGINE_STATE_DIR=' . $overrideDir . DIRECTORY_SEPARATOR);

        $root      = sys_get_temp_dir();
        $clean     = rtrim($root, DIRECTORY_SEPARATOR);
        $canonical = realpath($clean) ?: $clean;
        $projectId = sha1($canonical);
        $expected  = rtrim($overrideDir, DIRECTORY_SEPARATOR)
            . DIRECTORY_SEPARATOR . $projectId
            . DIRECTORY_SEPARATOR . '.flow-engine';

        $this->assertSame($expected, StateDirectory::forProjectRoot($root));
    }
}
