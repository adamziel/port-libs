<?php

declare(strict_types=1);

namespace PortLibs\Syncthing;

final class PullIterationRunner
{
    public const MAX_ITERATIONS = 3;

    /**
     * @var list<array{try:int, changed:int, tempPullErrors:int}>
     */
    private array $iterationSummaries = [];

    public function __construct(
        private readonly FolderErrorTracker $folderErrors,
        private readonly int $maxIterations = self::MAX_ITERATIONS,
    ) {
        if ($this->maxIterations < 1) {
            throw new \InvalidArgumentException('Max pull iterations must be at least one');
        }
    }

    /**
     * @param callable(int, FolderErrorTracker): int $pullerIteration
     */
    public function run(callable $pullerIteration): FolderPullResult
    {
        $this->folderErrors->startPull();
        $this->iterationSummaries = [];
        $changed = 0;

        for ($try = 1; $try <= $this->maxIterations; ++$try) {
            $this->folderErrors->startPullerIteration();
            $changed = $pullerIteration($try, $this->folderErrors);
            if (!is_int($changed) || $changed < 0) {
                throw new \UnexpectedValueException('Puller iteration must return a non-negative changed count');
            }

            $this->iterationSummaries[] = [
                'try' => $try,
                'changed' => $changed,
                'tempPullErrors' => count($this->folderErrors->tempPullErrors()),
            ];

            if ($changed === 0) {
                break;
            }
        }

        return $this->folderErrors->completePull($changed);
    }

    /**
     * @return list<array{try:int, changed:int, tempPullErrors:int}>
     */
    public function iterationSummaries(): array
    {
        return $this->iterationSummaries;
    }
}
