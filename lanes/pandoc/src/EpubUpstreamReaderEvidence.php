<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class EpubUpstreamReaderEvidence
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const EXPECTED_UPSTREAM_COMMIT = '4f5226df4faa0d66dd2c089465b13886360ab3c2';
    public const TOOL_NAME = 'pandoc-epub-reader-evidence';
    public const STATUS_COMPLETED = 'completed-upstream-epub-reader-evidence';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped-missing-upstream-epub-root';

    private readonly string $repoRoot;
    private readonly string $upstreamRoot;
    private readonly ?string $fixtureBase;

    public function __construct(string $repoRoot, string $upstreamRoot = self::DEFAULT_RELATIVE_UPSTREAM_ROOT, ?string $fixtureBase = null)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($upstreamRoot === '') {
            throw new \InvalidArgumentException('Upstream root must not be empty');
        }
        if ($fixtureBase === '') {
            throw new \InvalidArgumentException('Fixture base must not be empty');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->upstreamRoot = $upstreamRoot;
        $this->fixtureBase = $fixtureBase;
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

        $readerTestPath = $root . '/test/Tests/Readers/EPUB.hs';
        $fixtureRoot = $this->absoluteFixtureBase() ?? $root;
        $readerCases = is_file($readerTestPath)
            ? self::parseReaderCasesFromSource((string) file_get_contents($readerTestPath))
            : [];
        $validationIssues = $this->validationIssues($root, $fixtureRoot, $readerCases);

        return [
            'schemaVersion' => 1,
            'tool' => self::TOOL_NAME,
            'status' => self::STATUS_COMPLETED,
            'upstream' => [
                'name' => 'jgm/pandoc',
                'root' => $this->displayPath($root),
                'commit' => $this->gitHead($root),
                'expectedCommit' => self::EXPECTED_UPSTREAM_COMMIT,
                'readerTestModule' => 'test/Tests/Readers/EPUB.hs',
                'fixtureDirectory' => 'test/epub',
                'resolvedFixtureBase' => $this->displayPath($fixtureRoot),
                'resolvedFixtureDirectory' => $this->fixtureDirectoryDisplayPath(),
                'readerSource' => 'src/Text/Pandoc/Readers/EPUB.hs',
                'readerSourceRequired' => !$this->hasExplicitFixtureBase(),
            ],
            'denominator' => [
                'mediaBagTestCount' => count($readerCases),
                'fixtureReferenceCount' => count($this->fixtureReferences($readerCases)),
                'expectedMediaItemCount' => $this->expectedMediaItemCount($readerCases),
                'readerCases' => $readerCases,
                'referencedFixtures' => $this->fixtureReferences($readerCases),
                'missingReferencedFiles' => $this->missingReferencedFiles($root, $fixtureRoot, $readerCases),
                'unreferencedEpubFixtures' => $this->unreferencedEpubFixtures($root, $fixtureRoot, $readerCases),
            ],
            'sourceInventory' => $this->sourceInventory($root),
            'validation' => [
                'status' => $validationIssues === [] ? 'valid-upstream-epub-reader-mediabag-denominator' : 'invalid-upstream-epub-reader-mediabag-denominator',
                'issues' => $validationIssues,
            ],
            'claim' => self::claim(),
            'claimBoundaries' => self::claimBoundaries(),
        ];
    }

    /**
     * @return list<array{name: string, epub: string, bagName: string, expectedBag: list<array{path: string, mime: string, size: int}>, expectedBagItemCount: int, expectedBagMissing: bool}>
     */
    public static function parseReaderCasesFromSource(string $source): array
    {
        $bagDefinitions = self::parseBagDefinitions($source);
        $cases = [];
        $pattern = '/\btestCase\s+"([^"]+)"\s*\(\s*testMediaBag\s+"([^"]+\.epub)"\s+([A-Za-z_][A-Za-z0-9_\']*)\s*\)/s';
        if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $bagName = (string) $match[3];
            $expectedBag = $bagDefinitions[$bagName] ?? [];
            $cases[] = [
                'name' => (string) $match[1],
                'epub' => (string) $match[2],
                'bagName' => $bagName,
                'expectedBag' => $expectedBag,
                'expectedBagItemCount' => count($expectedBag),
                'expectedBagMissing' => !array_key_exists($bagName, $bagDefinitions),
            ];
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
            'Pandoc EPUB reader evidence',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Upstream: ' . (string) ($upstream['commit'] ?? 'unknown')
                . ' expected=' . (string) ($upstream['expectedCommit'] ?? self::EXPECTED_UPSTREAM_COMMIT),
            'Media bag tests: ' . (int) ($denominator['mediaBagTestCount'] ?? 0),
            'Referenced EPUB fixtures: ' . (int) ($denominator['fixtureReferenceCount'] ?? 0),
            'Expected media items: ' . (int) ($denominator['expectedMediaItemCount'] ?? 0),
            'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
            'No upstream Haskell/Cabal runner result, EPUB writer parity, or full EPUB feature parity is asserted.',
        ]) . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredMediaBagTestCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['mediaBagTestCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredFixtureReferenceCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['fixtureReferenceCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasRequiredExpectedMediaItemCount(array $report, int $requiredCount): bool
    {
        $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];

        return (int) ($denominator['expectedMediaItemCount'] ?? -1) === $requiredCount;
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasNoValidationIssues(array $report): bool
    {
        $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];

        return ($validation['status'] ?? null) === 'valid-upstream-epub-reader-mediabag-denominator'
            && ($validation['issues'] ?? null) === [];
    }

    private static function claim(): string
    {
        return 'Parses the pinned upstream Tests.Readers.EPUB module to establish the current EPUB reader media-bag test denominator and expected media directory tuples.';
    }

    /**
     * @return array<string, list<string>>
     */
    private static function claimBoundaries(): array
    {
        return [
            'doesAssert' => [
                'the count and fixture paths of upstream EPUB media-bag tests in Tests.Readers.EPUB',
                'the expected media-bag path, MIME type, and byte-size tuples embedded in the upstream test module',
                'that every referenced EPUB fixture exists in the upstream checkout or explicit checked-in fixture base',
                'that the upstream EPUB reader source file is present when validating a full upstream checkout without an explicit checked-in fixture base',
            ],
            'doesNotAssert' => [
                'that upstream Haskell/Cabal/Tasty tests were executed',
                'that local PHP output matches upstream media-bag output',
                'EPUB writer parity',
                'full EPUB feature parity beyond the upstream reader media-bag tests',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDenominator(): array
    {
        return [
            'mediaBagTestCount' => 0,
            'fixtureReferenceCount' => 0,
            'expectedMediaItemCount' => 0,
            'readerCases' => [],
            'referencedFixtures' => [],
            'missingReferencedFiles' => [],
            'unreferencedEpubFixtures' => [],
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
     * @return array<string, list<array{path: string, mime: string, size: int}>>
     */
    private static function parseBagDefinitions(string $source): array
    {
        $definitions = [];
        if (preg_match_all('/^([A-Za-z_][A-Za-z0-9_\']*)\s*=\s*(\[[^\]]*\])/m', $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $definitions[(string) $match[1]] = self::parseBagTuples((string) $match[2]);
        }

        return $definitions;
    }

    /**
     * @return list<array{path: string, mime: string, size: int}>
     */
    private static function parseBagTuples(string $source): array
    {
        $items = [];
        if (preg_match_all('/\("([^"]+)"\s*,\s*"([^"]+)"\s*,\s*(\d+)\)/', $source, $matches, PREG_SET_ORDER) === false) {
            return [];
        }

        foreach ($matches as $match) {
            $items[] = [
                'path' => (string) $match[1],
                'mime' => strtolower((string) $match[2]),
                'size' => (int) $match[3],
            ];
        }

        usort(
            $items,
            static fn (array $left, array $right): int => [$left['path'], $left['mime'], $left['size']] <=> [$right['path'], $right['mime'], $right['size']]
        );

        return $items;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function fixtureReferences(array $readerCases): array
    {
        $seen = [];
        $references = [];
        foreach ($readerCases as $case) {
            $fixture = is_string($case['epub'] ?? null) ? $case['epub'] : '';
            if ($fixture === '' || isset($seen[$fixture])) {
                continue;
            }
            $seen[$fixture] = true;
            $references[] = $fixture;
        }

        return $references;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     */
    private function expectedMediaItemCount(array $readerCases): int
    {
        $count = 0;
        foreach ($readerCases as $case) {
            $expectedBag = $case['expectedBag'] ?? [];
            if (is_array($expectedBag)) {
                $count += count($expectedBag);
            }
        }

        return $count;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<array{path: string}>
     */
    private function missingReferencedFiles(string $root, string $fixtureRoot, array $readerCases): array
    {
        $missing = [];
        foreach ($this->fixtureReferences($readerCases) as $fixture) {
            $path = $this->fixturePath($root, $fixtureRoot, $fixture);
            if (!is_file($path)) {
                $missing[] = ['path' => $this->fixtureDisplayPath($fixture)];
            }
        }

        return $missing;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function unreferencedEpubFixtures(string $root, string $fixtureRoot, array $readerCases): array
    {
        $fixtureDirectory = $this->fixtureDirectory($root, $fixtureRoot);
        if (!is_dir($fixtureDirectory)) {
            return [];
        }

        $referenced = array_fill_keys($this->fixtureReferences($readerCases), true);
        $unreferenced = [];
        foreach (glob($fixtureDirectory . '/*.epub') ?: [] as $path) {
            $relative = 'epub/' . basename($path);
            if (!isset($referenced[$relative])) {
                $unreferenced[] = $relative;
            }
        }
        sort($unreferenced, SORT_STRING);

        return $unreferenced;
    }

    /**
     * @param list<array<string, mixed>> $readerCases
     * @return list<string>
     */
    private function validationIssues(string $root, string $fixtureRoot, array $readerCases): array
    {
        $issues = [];
        if (!is_file($root . '/test/Tests/Readers/EPUB.hs')) {
            $issues[] = 'missing-reader-test-module';
        }
        if (!$this->hasExplicitFixtureBase() && !is_file($root . '/src/Text/Pandoc/Readers/EPUB.hs')) {
            $issues[] = 'missing-reader-source';
        }
        if ($readerCases === []) {
            $issues[] = 'no-epub-mediabag-tests';
        }

        foreach ($readerCases as $case) {
            if (($case['expectedBagMissing'] ?? false) === true) {
                $issues[] = 'missing-expected-mediabag-definition';
                break;
            }
        }

        if ($this->missingReferencedFiles($root, $fixtureRoot, $readerCases) !== []) {
            $issues[] = 'missing-referenced-fixture-files';
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
            'test/Tests/Readers/EPUB.hs',
            'src/Text/Pandoc/Readers/EPUB.hs',
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
            'readerSourceRequired' => !$this->hasExplicitFixtureBase(),
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

    private function absoluteFixtureBase(): ?string
    {
        if (!$this->hasExplicitFixtureBase()) {
            return null;
        }

        $fixtureBase = (string) $this->fixtureBase;
        if (str_starts_with($fixtureBase, DIRECTORY_SEPARATOR)) {
            return rtrim($fixtureBase, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . trim($fixtureBase, DIRECTORY_SEPARATOR);
    }

    private function hasExplicitFixtureBase(): bool
    {
        return is_string($this->fixtureBase) && $this->fixtureBase !== '';
    }

    private function fixturePath(string $root, string $fixtureRoot, string $fixture): string
    {
        return $this->hasExplicitFixtureBase()
            ? $fixtureRoot . '/' . $fixture
            : $root . '/test/' . $fixture;
    }

    private function fixtureDirectory(string $root, string $fixtureRoot): string
    {
        return $this->hasExplicitFixtureBase()
            ? $fixtureRoot . '/epub'
            : $root . '/test/epub';
    }

    private function fixtureDisplayPath(string $fixture): string
    {
        return $this->hasExplicitFixtureBase() ? $fixture : 'test/' . $fixture;
    }

    private function fixtureDirectoryDisplayPath(): string
    {
        return $this->hasExplicitFixtureBase() ? 'epub' : 'test/epub';
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
