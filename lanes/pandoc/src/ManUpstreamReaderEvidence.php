<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class ManUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-man-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-man-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-man-root';

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
                'validation' => [
                    'status' => 'not-evaluated-missing-upstream-root',
                    'issues' => ['missing-upstream-root'],
                ],
                'claim' => self::claim(),
                'claimBoundaries' => self::claimBoundaries(),
            ];
        }

        $readerTestPath = $root . '/test/Tests/Readers/Man.hs';
        $readerCases = is_file($readerTestPath)
            ? self::parseReaderCasesFromSource((string) file_get_contents($readerTestPath))
            : [];
        $validationIssues = $this->validationIssues($root, $readerCases);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/Man.hs',
                'readerSource' => 'src/Text/Pandoc/Readers/Man.hs',
            ],
            'denominator' => [
                'readerUnitCaseCount' => count($readerCases),
                'readerCases' => $readerCases,
            ],
            'sourceInventory' => $this->sourceInventory($root),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-man-reader-denominator' : 'invalid-upstream-man-reader-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return list<array{name: string}>
     */
    public static function parseReaderCasesFromSource(string $source): array
    {
        $cases = [];
        if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"\\s*=:/', $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $cases[] = ['name' => self::decodeHaskellString((string) $match[1])];
        }

        return $cases;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
        $upstream = is_array($report['upstream'] ?? null) ? $report['upstream'] : [];

        return implode(PHP_EOL, [
            'Pandoc man reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Reader unit cases: ' . (int) ($denominator['readerUnitCaseCount'] ?? 0),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result, mdoc parity, or full roff parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredReaderUnitCaseCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['readerUnitCaseCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-man-reader-denominator'
            && ($validation['issues'] ?? null) === [];
    }

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.Man module to establish the current roff man reader unit-test denominator.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and names of upstream roff man reader unit cases in Tests.Readers.Man',
                'that the upstream Man reader source file is present in the pinned sparse checkout',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that local PHP output matches upstream output',
                'mdoc reader parity',
                'full roff/man feature parity beyond the upstream Man reader unit cases',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'readerUnitCaseCount' => 0,
            'readerCases' => [],
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

    private static function decodeHaskellString(string $value): string
    {
        return strtr($value, [
            '\\"' => '"',
            '\\\\' => '\\',
            '\\n' => "\n",
            '\\t' => "\t",
        ]);
    }

    /**
     * @param list<array{name: string}> $readerCases
     * @return list<string>
     */
    private function validationIssues(string $root, array $readerCases): array
    {
        $issues = [];
        if (!is_file($root . '/test/Tests/Readers/Man.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!is_file($root . '/src/Text/Pandoc/Readers/Man.hs')) {
            $issues[] = 'missing-reader-source';
        }
        if ($readerCases === []) {
            $issues[] = 'no-man-reader-unit-cases';
        }

        return array_values(array_unique($issues));
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceInventory(string $root): array
    {
        $files = [];
        foreach ([
            'test/Tests/Readers/Man.hs',
            'src/Text/Pandoc/Readers/Man.hs',
        ] as $relativePath) {
            $path = $root . '/' . $relativePath;
            $present = is_file($path);
            $files[] = [
                'path' => $relativePath,
                'present' => $present,
                'lineCount' => $present ? $this->lineCount($path) : 0,
            ];
        }

        return [
            'files' => $files,
            'presentFileCount' => count(array_filter($files, static fn (array $file): bool => $file['present'] === true)),
            'missingFileCount' => count(array_filter($files, static fn (array $file): bool => $file['present'] === false)),
            'presentLineCount' => array_sum(array_map(static fn (array $file): int => (int) $file['lineCount'], $files)),
        ];
    }

    private function lineCount(string $path): int
    {
        $contents = file_get_contents($path);
        if (!is_string($contents) || $contents === '') {
            return 0;
        }

        return substr_count($contents, "\n") + (str_ends_with($contents, "\n") ? 0 : 1);
    }

    private function absoluteUpstreamRoot(): string
    {
        if (str_starts_with($this->upstreamRoot, DIRECTORY_SEPARATOR)) {
            return rtrim($this->upstreamRoot, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($this->upstreamRoot, DIRECTORY_SEPARATOR);
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
        $command = 'git -C ' . escapeshellarg($root) . ' rev-parse HEAD 2>/dev/null';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0 || $output === []) {
            return null;
        }

        return trim((string) $output[0]) ?: null;
    }
}
