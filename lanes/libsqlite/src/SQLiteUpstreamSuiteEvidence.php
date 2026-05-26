<?php

declare(strict_types=1);

namespace PortLibs\LibSqlite;

final class SQLiteUpstreamSuiteEvidence
{
    /**
     * @param array<string, mixed> $manifest
     */
    public function __construct(private readonly array $manifest)
    {
    }

    public static function fromManifestPath(string $path): self
    {
        $json = file_get_contents($path);
        if ($json === false) {
            throw new \InvalidArgumentException("Unable to read upstream manifest: {$path}");
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException("Unable to decode upstream manifest JSON: {$path}");
        }

        return new self($decoded);
    }

    /**
     * @return array<string, mixed>
     */
    public function denominatorSummary(): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? null;
        if (!is_array($denominator)) {
            throw new \InvalidArgumentException('Manifest benchmarkDenominator must be an object');
        }

        $inventory = $denominator['inventory'] ?? [];
        if (!is_array($inventory)) {
            $inventory = [];
        }

        $runner = $denominator['runnerStatus'] ?? [];
        if (!is_array($runner)) {
            $runner = [];
        }

        $veryquick = $this->parseVeryquickResult($runner['results']['fullVeryquick'] ?? null);

        return [
            'status' => $denominator['status'] ?? 'unknown',
            'total' => (int) ($denominator['total'] ?? 0),
            'mapped' => (int) ($denominator['mapped'] ?? 0),
            'inventory_units' => [
                'testDirectoryTclTests' => (int) ($inventory['testDirectoryTclTests'] ?? 0),
                'extensionTclTests' => (int) ($inventory['extensionTclTests'] ?? 0),
                'extensionNestedTclTests' => (int) ($inventory['extensionNestedTclTests'] ?? 0),
                'testDirectoryTclHarnessFiles' => (int) ($inventory['testDirectoryTclHarnessFiles'] ?? 0),
                'testDirectoryCPrograms' => (int) ($inventory['testDirectoryCPrograms'] ?? 0),
                'srcTestCOrHeaderHelpers' => (int) ($inventory['srcTestCOrHeaderHelpers'] ?? 0),
                'mptestFiles' => (int) ($inventory['mptestFiles'] ?? 0),
                'toolTestPrograms' => (int) ($inventory['toolTestPrograms'] ?? 0),
                'toolTestishFiles' => (int) ($inventory['toolTestishFiles'] ?? 0),
            ],
            'veryquick' => [
                'executed' => (bool) ($runner['executed'] ?? false),
                'scripts' => $veryquick['scripts'],
                'tests' => $veryquick['tests'],
                'errors' => $veryquick['errors'],
            ],
            'warning' => $denominator['warning'] ?? '',
        ];
    }

    /**
     * @return array{scripts: int, tests: int, errors: int}
     */
    private function parseVeryquickResult(mixed $result): array
    {
        if (!is_string($result)) {
            return ['scripts' => 0, 'tests' => 0, 'errors' => 0];
        }

        if (preg_match('/Passed\s+(\d+)\s+scripts\s+with\s+(\d+)\s+errors\s+out\s+of\s+(\d+)\s+tests/', $result, $matches) !== 1) {
            return ['scripts' => 0, 'tests' => 0, 'errors' => 0];
        }

        return [
            'scripts' => (int) $matches[1],
            'errors' => (int) $matches[2],
            'tests' => (int) $matches[3],
        ];
    }

    /**
     * @param list<string> $scripts
     * @return array<string, mixed>
     */
    public function focusedSubsetPlan(array $scripts, ?string $repoRoot = null): array
    {
        $denominator = $this->manifest['benchmarkDenominator'] ?? [];
        $runner = is_array($denominator) && is_array($denominator['runnerStatus'] ?? null)
            ? $denominator['runnerStatus']
            : [];
        $buildDirectory = is_string($runner['buildDirectory'] ?? null)
            ? $runner['buildDirectory']
            : '.upstream-cache/libsqlite-build-port-libsqlite';

        $root = $repoRoot ?? dirname(__DIR__, 3);
        $absoluteBuildDirectory = $root . '/' . $buildDirectory;
        $testfixture = $absoluteBuildDirectory . '/testfixture';
        $testrunner = $root . '/.upstream-cache/libsqlite/test/testrunner.tcl';

        $command = 'cd ' . $buildDirectory
            . ' && ./testfixture ../libsqlite/test/testrunner.tcl --jobs 1 --stop-on-error veryquick';
        if ($scripts !== []) {
            $command .= ' ' . implode(' ', $scripts);
        }

        $missing = [];
        if (!is_dir($absoluteBuildDirectory)) {
            $missing[] = $buildDirectory;
        }
        if (!is_file($testfixture)) {
            $missing[] = $buildDirectory . '/testfixture';
        }
        if (!is_file($testrunner)) {
            $missing[] = '.upstream-cache/libsqlite/test/testrunner.tcl';
        }

        return [
            'command' => $command,
            'scripts' => array_values($scripts),
            'runnable' => $missing === [],
            'skip_reason' => $missing === [] ? null : 'upstream cache/testfixture not hydrated in this worktree: missing ' . implode(', ', $missing),
        ];
    }
}
