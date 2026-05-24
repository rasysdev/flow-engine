<?php

namespace FlowEngine\Application\DTO;

/**
 * Result of the PredictViolations use case.
 *
 * When `hasBaseline` is false, `newViolations` mirrors `currentViolations`
 * (every violation is considered "new" because there is no reference to compare against)
 * and `resolvedViolations` is empty.
 *
 * @api
 */
final readonly class PredictViolationsDTO
{
    /**
     * @param array<int, array>  $currentViolations  All violations in the current analysis
     * @param array<int, array>  $newViolations       Violations absent from the baseline (or all current when no baseline)
     * @param array<int, array>  $resolvedViolations  Violations present in baseline but fixed now
     * @param int                $totalCurrent        Count of current violations
     * @param int                $totalNew            Count of new violations
     * @param int                $totalResolved       Count of resolved violations
     * @param bool               $hasBaseline         Whether a baseline snapshot was provided
     * @param string|null        $baselineLabel       Snapshot label used as baseline, or null
     * @param bool               $shouldFail          True when the gate policy is triggered
     * @param string             $failOn              Policy used: 'any' | 'new' | 'critical'
     * @param bool               $isClean             True when there are zero current violations
     */
    public function __construct(
        public array   $currentViolations,
        public array   $newViolations,
        public array   $resolvedViolations,
        public int     $totalCurrent,
        public int     $totalNew,
        public int     $totalResolved,
        public bool    $hasBaseline,
        public ?string $baselineLabel,
        public bool    $shouldFail,
        public string  $failOn,
        public bool    $isClean,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'isClean'             => $this->isClean,
            'shouldFail'          => $this->shouldFail,
            'failOn'              => $this->failOn,
            'hasBaseline'         => $this->hasBaseline,
            'baselineLabel'       => $this->baselineLabel,
            'totalCurrent'        => $this->totalCurrent,
            'totalNew'            => $this->totalNew,
            'totalResolved'       => $this->totalResolved,
            'currentViolations'   => $this->currentViolations,
            'newViolations'       => $this->newViolations,
            'resolvedViolations'  => $this->resolvedViolations,
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
