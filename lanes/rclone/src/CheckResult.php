<?php

declare(strict_types=1);

namespace PortLibs\Rclone;

final class CheckResult
{
    /**
     * @param list<string> $matches
     * @param list<string> $differ
     * @param list<string> $missingOnSource
     * @param list<string> $missingOnTarget
     * @param list<string> $errors
     * @param array<string, string> $errorMessages
     */
    public function __construct(
        public readonly array $matches,
        public readonly array $differ,
        public readonly array $missingOnSource,
        public readonly array $missingOnTarget,
        public readonly array $errors = [],
        public readonly array $errorMessages = [],
    ) {
    }

    public function differences(): int
    {
        return count($this->differ) + count($this->missingOnSource) + count($this->missingOnTarget);
    }

    public function matches(): int
    {
        return count($this->matches);
    }

    public function errors(): int
    {
        return count($this->errors);
    }

    /**
     * @return list<string>
     */
    public function combinedLines(): array
    {
        $lines = [];
        foreach ($this->missingOnSource as $path) {
            $lines[] = '- ' . $path;
        }
        foreach ($this->missingOnTarget as $path) {
            $lines[] = '+ ' . $path;
        }
        foreach ($this->differ as $path) {
            $lines[] = '* ' . $path;
        }
        foreach ($this->errors as $path) {
            $lines[] = '! ' . $path;
        }
        foreach ($this->matches as $path) {
            $lines[] = '= ' . $path;
        }

        sort($lines, SORT_STRING);

        return $lines;
    }
}
