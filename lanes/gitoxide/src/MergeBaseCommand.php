<?php

declare(strict_types=1);

namespace PortLibs\Gitoxide;

final class MergeBaseCommand
{
    /**
     * Format resolved merge-base ids like the upstream `gitoxide merge-base`
     * plumbing command's human output.
     *
     * @param list<string> $others
     */
    public static function humanOutput(MergeBaseFinder $finder, string $first, array $others): string
    {
        $bases = $finder->mergeBasesAgainst($first, $others);
        if ($bases === []) {
            throw new \RuntimeException(
                'No base found for ' . $first . ' and ' . implode(', ', $others),
            );
        }

        return implode("\n", $bases) . "\n";
    }
}
