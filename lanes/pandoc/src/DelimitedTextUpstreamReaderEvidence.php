<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DelimitedTextUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-delimited-text-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-delimited-text-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-delimited-text-root';
    public const CHECKED_IN_CURRENT_FIXTURE_DIRECTORY = 'lanes/pandoc/fixtures/upstream-current-csv-reader';
    public const EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT = 2;
    public const EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT = 0;

    private const CHECKED_IN_CURRENT_CSV_FIXTURES = [
        'csv.md' => [
            'role' => 'direct-csv-command-reader-native-output',
            'upstreamPath' => 'test/command/csv.md',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/csv.md',
            'sha256' => '42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6',
            'bytes' => 2719,
        ],
        '01.csv' => [
            'role' => 'direct-csv-command-reader-input-fixture',
            'upstreamPath' => 'test/command/01.csv',
            'checkedInPath' => 'lanes/pandoc/fixtures/upstream-current-csv-reader/01.csv',
            'sha256' => '257c619e19786fddf7685a31a45f6495446a5213083540d09ecba6ce7f1e62cd',
            'bytes' => 47,
        ],
    ];

    private const SOURCE_FILES = [
        'src/Text/Pandoc/CSV.hs',
        'src/Text/Pandoc/Readers/CSV.hs',
    ];

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;

    public function __construct(string $repoRoot, string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $root = $this->absoluteUpstreamRoot();
        if (!is_dir($root)) {
            return [
                'schemaVersion' => 1,
                'tool' => self::TOOL_NAME,
                'status' => self::STATUS_SKIPPED_MISSING_SOURCE,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'root' => $this->displayPath($root),
                    'commit' => null,
                    'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                ],
                'denominator' => $this->emptyDenominator(),
                'sourceInventory' => $this->emptySourceInventory(),
                'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $upstreamFixtures = $this->upstreamFixtureEvidence($root);
        $validationIssues = $this->validationIssues($upstreamFixtures, $this->sourceInventory($root));

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'denominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => [],
                'upstreamFixtures' => $upstreamFixtures,
                'parserOptionFixtureCount' => 5,
                'parserOptionFixtures' => [
                    'comma-delimiter-no-header',
                    'space-delimiter-single-quote',
                    'backslash-escaped-quote',
                    'keep-space-after-delimiter',
                    'semicolon-delimiter-multiline-cell',
                ],
            ],
            'sourceInventory' => $this->sourceInventory($root),
            'staticCurrentEvidence' => self::checkedInCurrentEvidence($this->repoRoot),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-delimited-text-reader-evidence' : 'invalid-upstream-delimited-text-reader-evidence',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function checkedInCurrentEvidence(string $repoRoot): array
    {
        $root = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $fixtureDirectory = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY);
        $issues = [];
        if (!is_dir($fixtureDirectory)) {
            $issues[] = 'missing-checked-in-current-fixture-directory';
        }

        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_CSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['checkedInPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                'upstreamPath' => (string) $snapshot['upstreamPath'],
                'checkedInFile' => $file,
            ];

            if (($file['present'] ?? false) !== true) {
                $issues[] = 'missing-checked-in-current-csv-fixture';
            } elseif (($file['sha256'] ?? null) !== $snapshot['sha256']) {
                $issues[] = 'checked-in-current-csv-fixture-sha256-mismatch';
            } elseif ((int) ($file['bytes'] ?? -1) !== (int) $snapshot['bytes']) {
                $issues[] = 'checked-in-current-csv-fixture-byte-count-mismatch';
            }
        }

        return [
            'kind' => 'static-checked-in-current-upstream-delimited-text-reader-fixture-evidence',
            'upstream' => [
                'name' => 'jgm/pandoc',
                'commit' => self::EXPECTED_UPSTREAM_COMMIT,
                'fixtureDirectory' => 'test/command',
                'readerSources' => self::SOURCE_FILES,
            ],
            'readerDenominator' => [
                'csvDirectFixtureCount' => count(self::CHECKED_IN_CURRENT_CSV_FIXTURES),
                'tsvDirectFixtureCount' => self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT,
                'fixtureScope' => 'direct CSV command reader fixtures in test/command',
                'csvDirectFixtures' => array_values(array_map(
                    static fn (array $fixture): string => (string) $fixture['upstreamPath'],
                    self::CHECKED_IN_CURRENT_CSV_FIXTURES
                )),
                'tsvDirectFixtures' => [],
            ],
            'checkedInFixtureDirectory' => self::CHECKED_IN_CURRENT_FIXTURE_DIRECTORY,
            'checkedInFixtureCount' => count($fixtures),
            'checkedInFixtures' => $fixtures,
            'validation' => [
                'status' => $issues === [] ? 'valid-checked-in-current-delimited-text-reader-evidence' : 'invalid-checked-in-current-delimited-text-reader-evidence',
                'issues' => array_values(array_unique($issues)),
            ],
            'claim' => 'Static gate binding Pandoc current CSV command-reader fixtures to checked-in SHA-256 and byte-count snapshots.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'the checked-in csv.md and 01.csv snapshots match the pinned upstream command fixture hashes',
                    'the upstream command corpus has two CSV direct-reader fixtures tracked by this PHP reader',
                    'there is no dedicated upstream TSV command fixture in this pinned corpus',
                ],
                'doesNotAssert' => [
                    'that upstream Haskell/Cabal/Tasty tests were executed',
                    'that RST csv-table integration is implemented locally',
                    'full CSV/TSV feature parity beyond the direct reader fixtures and local parser-option cases',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];
        $staticEvidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $staticValidation = is_array($staticEvidence['validation'] ?? null) ? $staticEvidence['validation'] : [];

        return implode(PHP_EOL, [
            'Pandoc delimited text reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'CSV direct fixtures: ' . (int) ($denominator['csvDirectFixtureCount'] ?? 0),
            'TSV direct fixtures: ' . (int) ($denominator['tsvDirectFixtureCount'] ?? 0),
            'Static current evidence: ' . (string) ($staticValidation['status'] ?? 'unknown')
                . ' checkedInFixtures=' . (int) ($staticEvidence['checkedInFixtureCount'] ?? 0),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result, RST csv-table integration, or full CSV/TSV feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredStaticCurrentEvidence(array $report): bool
    {
        $evidence = is_array($report['staticCurrentEvidence'] ?? null) ? $report['staticCurrentEvidence'] : [];
        $validation = is_array($evidence['validation'] ?? null) ? $evidence['validation'] : [];
        $denominator = is_array($evidence['readerDenominator'] ?? null) ? $evidence['readerDenominator'] : [];

        return ($validation['status'] ?? null) === 'valid-checked-in-current-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === []
            && (int) ($denominator['csvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT
            && (int) ($denominator['tsvDirectFixtureCount'] ?? -1) === self::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-delimited-text-reader-evidence'
            && ($validation['issues'] ?? null) === [];
    }

    private static function claim(): string
    {
        return 'Tracks the current upstream direct CSV command-reader fixtures and the absence of dedicated TSV command fixtures for the delimited text reader.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and file identities of upstream direct CSV command-reader fixtures tracked locally',
                'that the current pinned upstream CSV reader source files are present when an upstream checkout is inspected',
                'that no dedicated TSV command fixture is available in the pinned direct-reader evidence set',
                'static checked-in current csv.md and 01.csv fixture identity when staticCurrentEvidence is valid',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that local PHP output matches every upstream CSV-adjacent command fixture',
                'RST csv-table reader integration',
                'full CSV/TSV feature parity beyond the direct reader fixture evidence',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'csvDirectFixtureCount' => 0,
            'tsvDirectFixtureCount' => 0,
            'fixtureScope' => 'direct CSV command reader fixtures in test/command',
            'csvDirectFixtures' => [],
            'tsvDirectFixtures' => [],
            'upstreamFixtures' => [],
            'parserOptionFixtureCount' => 0,
            'parserOptionFixtures' => [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptySourceInventory(): array
    {
        return [
            'files' => [],
            'presentFileCount' => 0,
            'missingFileCount' => 0,
            'presentLineCount' => 0,
        ];
    }

    /**
     * @return list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}>
     */
    private function upstreamFixtureEvidence(string $root): array
    {
        $fixtures = [];
        foreach (self::CHECKED_IN_CURRENT_CSV_FIXTURES as $name => $snapshot) {
            $file = self::snapshotFileEvidence(
                $root,
                (string) $snapshot['upstreamPath'],
                (string) $snapshot['sha256'],
                (int) $snapshot['bytes']
            );
            $fixtures[] = [
                'name' => (string) $name,
                'role' => (string) $snapshot['role'],
                ...$file,
            ];
        }

        return $fixtures;
    }

    /**
     * @return array{files: list<array{path: string, present: bool, bytes: ?int, lineCount: ?int}>, presentFileCount: int, missingFileCount: int, presentLineCount: int}
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach (self::SOURCE_FILES as $path) {
            $absolute = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
            $present = is_file($absolute);
            $contents = $present ? file_get_contents($absolute) : false;
            $bytes = $present ? filesize($absolute) : false;
            $files[] = [
                'path' => $path,
                'present' => $present,
                'bytes' => is_int($bytes) ? $bytes : null,
                'lineCount' => is_string($contents) ? substr_count($contents, "\n") + ($contents === '' || str_ends_with($contents, "\n") ? 0 : 1) : null,
            ];
        }

        $present = array_values(array_filter($files, static fn (array $file): bool => ($file['present'] ?? false) === true));

        return [
            'files' => $files,
            'presentFileCount' => count($present),
            'missingFileCount' => count($files) - count($present),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) ($file['lineCount'] ?? 0), $present)),
        ];
    }

    /**
     * @param list<array{name: string, role: string, path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}> $upstreamFixtures
     * @param array<string, mixed> $sourceInventory
     * @return list<string>
     */
    private function validationIssues(array $upstreamFixtures, array $sourceInventory): array
    {
        $issues = [];
        foreach ($upstreamFixtures as $fixture) {
            if (($fixture['present'] ?? false) !== true) {
                $issues[] = 'missing-upstream-csv-command-fixture';
            } elseif (($fixture['sha256'] ?? null) !== ($fixture['expectedSha256'] ?? null)) {
                $issues[] = 'upstream-csv-command-fixture-sha256-mismatch';
            } elseif ((int) ($fixture['bytes'] ?? -1) !== (int) ($fixture['expectedBytes'] ?? -2)) {
                $issues[] = 'upstream-csv-command-fixture-byte-count-mismatch';
            }
        }

        if ((int) ($sourceInventory['missingFileCount'] ?? 0) > 0) {
            $issues[] = 'missing-upstream-delimited-text-reader-source';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array{path: string, present: bool, sha256: ?string, expectedSha256: string, bytes: ?int, expectedBytes: int}
     */
    private static function snapshotFileEvidence(string $root, string $relativePath, string $expectedSha256, int $expectedBytes): array
    {
        $path = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $present = is_file($path);
        $sha256 = $present ? hash_file('sha256', $path) : false;
        $bytes = $present ? filesize($path) : false;

        return [
            'path' => $relativePath,
            'present' => $present,
            'sha256' => is_string($sha256) ? $sha256 : null,
            'expectedSha256' => $expectedSha256,
            'bytes' => is_int($bytes) ? $bytes : null,
            'expectedBytes' => $expectedBytes,
        ];
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->upstreamRoot);
    }

    private function displayPath(string $path): string
    {
        if (str_starts_with($path, $this->repoRoot . DIRECTORY_SEPARATOR)) {
            return substr($path, strlen($this->repoRoot) + 1);
        }

        return $path;
    }

    private function gitHead(string $root): ?string
    {
        $head = $root . '/.git/HEAD';
        if (!is_file($head)) {
            return null;
        }

        $contents = trim((string) file_get_contents($head));
        if (str_starts_with($contents, 'ref: ')) {
            $refPath = $root . '/.git/' . substr($contents, 5);
            return is_file($refPath) ? trim((string) file_get_contents($refPath)) : null;
        }

        return preg_match('/^[0-9a-f]{40}$/', $contents) === 1 ? $contents : null;
    }
}
