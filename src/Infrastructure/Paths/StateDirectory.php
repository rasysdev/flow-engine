<?php

namespace FlowEngine\Infrastructure\Paths;

/**
 * Centralizes where Flow Engine persists state for an analyzed project.
 *
 * Resolution order:
 *   1. FLOW_ENGINE_STATE_DIR (if set and non-empty)
 *      -> <FLOW_ENGINE_STATE_DIR>/<sha1(canonicalRoot)>/.flow-engine
 *   2. $HOME (or getenv('HOME'))
 *      -> $HOME/.cache/flow-engine/<sha1(canonicalRoot)>/.flow-engine
 *   3. Fallback (no HOME, e.g. headless CI)
 *      -> sys_get_temp_dir()/flow-engine/<sha1(canonicalRoot)>/.flow-engine
 *
 * The project root is canonicalized via realpath() before hashing so that
 * equivalent paths ("." vs absolute, symlinks, trailing slash) resolve to
 * the same state directory. The project root is never used as the state
 * directory, so Flow Engine never writes .flow-engine/ inside the analyzed
 * project.
 */
final class StateDirectory
{
    public static function forProjectRoot(string $projectRoot): string
    {
        $projectRoot = rtrim($projectRoot, DIRECTORY_SEPARATOR);
        $canonical   = realpath($projectRoot) ?: $projectRoot;
        $projectId   = sha1($canonical);

        $override = getenv('FLOW_ENGINE_STATE_DIR') ?: null;

        if ($override !== null && trim($override) !== '') {
            $base = rtrim($override, DIRECTORY_SEPARATOR);
            return $base . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . '.flow-engine';
        }

        $home = getenv('HOME') ?: null;

        if ($home !== null && trim($home) !== '') {
            $base = rtrim($home, DIRECTORY_SEPARATOR);
            return $base . DIRECTORY_SEPARATOR . '.cache' . DIRECTORY_SEPARATOR
                . 'flow-engine' . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . '.flow-engine';
        }

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'flow-engine' . DIRECTORY_SEPARATOR . $projectId . DIRECTORY_SEPARATOR . '.flow-engine';
    }
}
