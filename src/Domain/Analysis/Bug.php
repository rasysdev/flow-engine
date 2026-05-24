<?php

namespace FlowEngine\Domain\Analysis;

/**
 * A detected potential bug in a node, weighted by graph impact.
 *
 * @api
 */
final readonly class Bug
{
    public const TYPE_NULL_DEREFERENCE     = 'null_dereference';
    public const TYPE_HARDCODED_CREDENTIAL = 'hardcoded_credential';

    public const TYPE_PYTHON_EXCEPTION_SWALLOWING = 'python_exception_swallowing';
    public const TYPE_PYTHON_MUTABLE_DEFAULT      = 'python_mutable_default_arg';
    public const TYPE_PYTHON_MISSING_RETURN_TYPE  = 'python_missing_return_type';
    public const TYPE_PYTHON_UNCHECKED_SUBPROCESS = 'python_unchecked_subprocess';

    public function __construct(
        public string $nodeId,
        public string $type,
        public string $description,
        public string $severity,
        public int    $bugScore,
        public float  $confidence,
        public string $file,
        public ?int   $line
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'nodeId'      => $this->nodeId,
            'type'        => $this->type,
            'description' => $this->description,
            'severity'    => $this->severity,
            'bugScore'    => $this->bugScore,
            'confidence'  => $this->confidence,
            'file'        => $this->file,
            'line'        => $this->line,
        ];
    }

    public static function severityFromScore(int $score): string
    {
        return match (true) {
            $score >= 70 => 'CRITICAL',
            $score >= 40 => 'HIGH',
            $score >= 20 => 'MEDIUM',
            default      => 'LOW',
        };
    }
}
