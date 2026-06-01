<?php

declare(strict_types=1);

namespace PortLibs\LightningCSS;

use InvalidArgumentException;

final class UpstreamRunnerEvidence
{
    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     passed:int,
     *     failed:int,
     *     filtered:int,
     *     dirtyCache:bool,
     *     output:string
     * }
     */
    public static function rustCargoTest(
        string $label,
        string $command,
        int $exitCode,
        string $output,
        bool $dirtyCache = false
    ): array {
        $summary = self::parseCargoSummary($output);
        $status = $exitCode === 0 && $summary['failed'] === 0 && $summary['passed'] > 0 ? 'passed' : 'failed';

        return [
            'runner' => 'rust',
            'label' => $label,
            'command' => $command,
            'status' => $status,
            'exitCode' => $exitCode,
            'passed' => $summary['passed'],
            'failed' => $summary['failed'],
            'filtered' => $summary['filtered'],
            'dirtyCache' => $dirtyCache,
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     expected:string,
     *     actual:string,
     *     output:string
     * }
     */
    public static function nodeNativeAddonSmoke(
        string $label,
        string $command,
        int $exitCode,
        string $output,
        string $expectedCss
    ): array {
        $actual = trim($output);

        return [
            'runner' => 'node-native',
            'label' => $label,
            'command' => $command,
            'status' => $exitCode === 0 && $actual === $expectedCss ? 'passed' : 'failed',
            'exitCode' => $exitCode,
            'expected' => $expectedCss,
            'actual' => $actual,
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     dependency:string,
     *     blocker:string,
     *     output:string
     * }
     */
    public static function moduleDependencyBlocker(
        string $runner,
        string $label,
        string $command,
        int $exitCode,
        string $output
    ): array {
        $dependency = self::missingModuleName($output);
        $status = $exitCode !== 0 && $dependency !== null ? 'blocked' : 'failed';

        return [
            'runner' => $runner,
            'label' => $label,
            'command' => $command,
            'status' => $status,
            'exitCode' => $exitCode,
            'dependency' => $dependency ?? '',
            'blocker' => $dependency === null ? '' : "missing Node module {$dependency}",
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @return array{
     *     runner:string,
     *     label:string,
     *     command:string,
     *     status:string,
     *     exitCode:int,
     *     tool:string,
     *     blocker:string,
     *     output:string
     * }
     */
    public static function missingCommandBlocker(
        string $runner,
        string $label,
        string $command,
        int $exitCode,
        string $output
    ): array {
        if (!preg_match('/(?:^|\\s)([A-Za-z0-9_.-]+):\\s+command not found\\b/', $output, $matches)) {
            throw new InvalidArgumentException('Output does not contain a shell command-not-found diagnostic.');
        }

        return [
            'runner' => $runner,
            'label' => $label,
            'command' => $command,
            'status' => 'blocked',
            'exitCode' => $exitCode,
            'tool' => $matches[1],
            'blocker' => "missing command {$matches[1]}",
            'output' => self::compactOutput($output),
        ];
    }

    /**
     * @param list<array<string, mixed>> $records
     * @return array{
     *     passed:int,
     *     blocked:int,
     *     failed:int,
     *     blockers:list<string>,
     *     passedLabels:list<string>,
     *     fullRunnerStatus:string,
     *     dependencyClosure:string
     * }
     */
    public static function summarize(array $records): array
    {
        $passed = 0;
        $blocked = 0;
        $failed = 0;
        $blockers = [];
        $passedLabels = [];

        foreach ($records as $record) {
            $status = $record['status'] ?? null;
            if ($status === 'passed') {
                $passed++;
                $passedLabels[] = (string) ($record['label'] ?? '');
                continue;
            }

            if ($status === 'blocked') {
                $blocked++;
                $blocker = (string) ($record['blocker'] ?? '');
                if ($blocker !== '') {
                    $blockers[$blocker] = true;
                }
                continue;
            }

            $failed++;
        }

        return [
            'passed' => $passed,
            'blocked' => $blocked,
            'failed' => $failed,
            'blockers' => array_keys($blockers),
            'passedLabels' => $passedLabels,
            'fullRunnerStatus' => $blocked === 0 && $failed === 0 ? 'complete' : ($passed > 0 ? 'partial' : 'blocked'),
            'dependencyClosure' => self::dependencyClosureText(array_keys($blockers)),
        ];
    }

    /**
     * @return array{passed:int, failed:int, filtered:int}
     */
    private static function parseCargoSummary(string $output): array
    {
        if (!preg_match(
            '/test result:\\s+\\w+\\.\\s+(\\d+) passed;\\s+(\\d+) failed;\\s+\\d+ ignored;\\s+\\d+ measured;\\s+(\\d+) filtered out;/',
            $output,
            $matches
        )) {
            throw new InvalidArgumentException('Cargo test output did not include a parseable test result summary.');
        }

        return [
            'passed' => (int) $matches[1],
            'failed' => (int) $matches[2],
            'filtered' => (int) $matches[3],
        ];
    }

    private static function missingModuleName(string $output): ?string
    {
        if (preg_match("/Cannot find (?:package|module) '([^']+)'/", $output, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private static function compactOutput(string $output): string
    {
        $output = trim($output);
        $output = preg_replace('/\\s+/', ' ', $output) ?? $output;

        return $output;
    }

    /**
     * @param list<string> $blockers
     */
    private static function dependencyClosureText(array $blockers): string
    {
        if ($blockers === []) {
            return 'No new support dependency is required.';
        }

        return 'No PHP support component is required; runner closure needs upstream dependency installation for '
            . implode(', ', $blockers)
            . '.';
    }
}
