<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxUpstreamRunnerPlan
{
    public const DEFAULT_RELATIVE_UPSTREAM_ROOT = '.upstream-cache/pandoc-current';
    public const PINNED_UPSTREAM_COMMIT = '0640c4c9859aa5a3ede082c190fcd5883c24ac83';
    public const STATUS_READY = 'preflight-ready-no-runner-executed';
    public const STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE = 'blocked-missing-docx-upstream-source';
    public const EVIDENCE_KIND = 'targeted-docx-runner-preflight-plan-only';
    public const SELECTED_TEST_INVENTORY_EVIDENCE_KIND = 'static-docx-selected-test-inventory-only';
    public const SELECTED_TEST_INVENTORY_STATUS_REPORTED = 'reported-static-docx-selected-test-inventory';
    public const SELECTED_TEST_INVENTORY_STATUS_BLOCKED = 'blocked-missing-docx-upstream-source';
    public const TASTY_PATTERN = '($2 == "Readers" || $2 == "Writers") && $3 == "Docx"';
    public const RUNNER_TARGET = 'test:test-pandoc';

    /** @var list<string> */
    private const REQUIRED_FILES = [
        'cabal.project',
        'pandoc.cabal',
        'test/test-pandoc.hs',
        'test/Tests/Readers/Docx.hs',
        'test/Tests/Writers/Docx.hs',
        'data/default.docx',
    ];

    /** @var list<string> */
    private const REQUIRED_DIRECTORIES = [
        'test/docx',
        'test/docx/golden',
    ];

    /** @var array<string, string> */
    private const WORKSPACE_ENVIRONMENT = [
        'CABAL_DIR' => '.port-libs/pandoc-runner/cabal-home',
        'CABAL_CONFIG' => '.port-libs/pandoc-runner/cabal-config/config',
        'XDG_CACHE_HOME' => '.port-libs/pandoc-runner/cache',
        'XDG_STATE_HOME' => '.port-libs/pandoc-runner/state',
        'TMPDIR' => '.port-libs/pandoc-runner/tmp',
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
        $this->upstreamRoot = $this->absolutePath($upstreamRoot);
    }

    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $sourcePreflight = $this->sourcePreflight();
        $ready = $sourcePreflight['missingFiles'] === [] && $sourcePreflight['missingDirectories'] === [];
        $selectedTestInventory = $this->selectedTestInventory($sourcePreflight, $ready);

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-upstream-runner-plan',
            'status' => $ready ? self::STATUS_READY : self::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE,
            'evidenceKind' => self::EVIDENCE_KIND,
            'runnerExecuted' => false,
            'resultRecorded' => false,
            'willExecute' => false,
            'claim' => 'Preflight-only plan for a future targeted upstream DOCX Tasty run; this is not an upstream DOCX runner result.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'required DOCX runner source paths and fixture directories to check before a targeted run',
                    'static selected DOCX reader/writer source and fixture inventory when the upstream checkout is hydrated',
                    'exact Cabal commands and workspace artifact paths a future runner slice can execute',
                    'result artifact contract required before recording upstream DOCX runner evidence',
                ],
                'doesNotAssert' => [
                    'that Cabal or upstream Pandoc tests were executed',
                    'that selected DOCX reader or writer tests pass',
                    'that local PHP DOCX parsing, writing, or package output matches upstream',
                    'full DOCX/OpenXML parity',
                ],
            ],
            'upstream' => [
                'pinnedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'expectedCheckoutRoot' => $this->displayPath($this->upstreamRoot),
            ],
            'runnerTarget' => self::RUNNER_TARGET,
            'tastyPattern' => self::TASTY_PATTERN,
            'sourcePreflight' => $sourcePreflight,
            'selectedTestInventory' => $selectedTestInventory,
            'workspace' => $this->workspace(),
            'commands' => $this->commands(),
            'resultArtifactContract' => $this->resultArtifactContract(),
            'activationGate' => $this->activationGate($ready),
            'executionPolicy' => 'This PHP tool emits preflight evidence and command descriptors only; it never invokes Cabal or upstream Pandoc tests.',
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX upstream runner preflight plan',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Evidence kind: ' . (string) ($report['evidenceKind'] ?? self::EVIDENCE_KIND),
            'Runner executed: ' . self::boolText($report['runnerExecuted'] ?? false),
            'Result recorded: ' . self::boolText($report['resultRecorded'] ?? false),
            'Upstream root: ' . (string) ($report['upstream']['expectedCheckoutRoot'] ?? ''),
            'Pinned upstream commit: ' . (string) ($report['upstream']['pinnedCommit'] ?? self::PINNED_UPSTREAM_COMMIT),
        ];

        $source = $report['sourcePreflight'] ?? [];
        if (is_array($source)) {
            $lines[] = 'Missing files: ' . implode(', ', array_map('strval', $source['missingFiles'] ?? []));
            $lines[] = 'Missing directories: ' . implode(', ', array_map('strval', $source['missingDirectories'] ?? []));
        }

        $inventory = $report['selectedTestInventory'] ?? [];
        if (is_array($inventory)) {
            $fixtureCounts = $inventory['fixtures']['counts'] ?? [];
            $lines[] = 'Selected inventory: '
                . (string) ($inventory['status'] ?? 'unknown')
                . '; static label hints='
                . (int) ($inventory['staticCandidateTestLabelCount'] ?? 0)
                . '; paired reader fixture stems='
                . (int) ($fixtureCounts['pairedRootDocxNativeStems'] ?? 0)
                . '; writer golden packages='
                . (int) ($fixtureCounts['goldenDocxPackageFiles'] ?? 0);
        }

        $artifact = $report['selectedTestInventoryArtifact'] ?? [];
        if (is_array($artifact) && ($artifact['written'] ?? false) === true) {
            $lines[] = 'Selected inventory artifact: ' . (string) ($artifact['path'] ?? '');
        }

        $commands = $report['commands'] ?? [];
        if (is_array($commands)) {
            foreach (['dependencyDryRun', 'listDocxTests', 'targetedDocxRun'] as $name) {
                $command = $commands[$name] ?? null;
                if (is_array($command)) {
                    $lines[] = $name . ': ' . (string) ($command['commandLine'] ?? '');
                }
            }
        }

        $lines[] = 'No upstream DOCX runner result or parity claim is asserted.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return list<string>
     */
    public static function requiredFiles(): array
    {
        return self::REQUIRED_FILES;
    }

    /**
     * @return list<string>
     */
    public static function requiredDirectories(): array
    {
        return self::REQUIRED_DIRECTORIES;
    }

    /**
     * @return array<string, mixed>
     */
    private function sourcePreflight(): array
    {
        $presentFiles = [];
        $missingFiles = [];
        foreach (self::REQUIRED_FILES as $relativePath) {
            $absolutePath = $this->upstreamPath($relativePath);
            if ($this->hasNonEmptyFile($absolutePath)) {
                $presentFiles[] = $relativePath;
            } else {
                $missingFiles[] = $relativePath;
            }
        }

        $presentDirectories = [];
        $missingDirectories = [];
        foreach (self::REQUIRED_DIRECTORIES as $relativePath) {
            $absolutePath = $this->upstreamPath($relativePath);
            if (is_dir($absolutePath)) {
                $presentDirectories[] = $relativePath;
            } else {
                $missingDirectories[] = $relativePath;
            }
        }

        return [
            'upstreamRoot' => $this->upstreamRoot,
            'upstreamRootDisplay' => $this->displayPath($this->upstreamRoot),
            'upstreamRootPresent' => is_dir($this->upstreamRoot),
            'requiredFiles' => self::REQUIRED_FILES,
            'presentFiles' => $presentFiles,
            'missingFiles' => $missingFiles,
            'requiredDirectories' => self::REQUIRED_DIRECTORIES,
            'presentDirectories' => $presentDirectories,
            'missingDirectories' => $missingDirectories,
            'artifactCounts' => [
                'rootDocxPackageFiles' => $this->countDirectFiles($this->upstreamPath('test/docx'), 'docx'),
                'rootNativeExpectedFiles' => $this->countDirectFiles($this->upstreamPath('test/docx'), 'native'),
                'goldenDocxPackageFiles' => $this->countDirectFiles($this->upstreamPath('test/docx/golden'), 'docx'),
            ],
            'packageBytePolicy' => 'filesystem names/counts only; preflight does not read DOCX package bytes',
        ];
    }

    /**
     * @param array<string, mixed> $sourcePreflight
     * @return array<string, mixed>
     */
    private function selectedTestInventory(array $sourcePreflight, bool $ready): array
    {
        $base = [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-upstream-runner-plan',
            'status' => $ready ? self::SELECTED_TEST_INVENTORY_STATUS_REPORTED : self::SELECTED_TEST_INVENTORY_STATUS_BLOCKED,
            'evidenceKind' => self::SELECTED_TEST_INVENTORY_EVIDENCE_KIND,
            'runnerExecuted' => false,
            'cabalExecuted' => false,
            'docxPackageBytesRead' => false,
            'generatedAt' => gmdate('Y-m-d'),
            'upstream' => [
                'pinnedCommit' => self::PINNED_UPSTREAM_COMMIT,
                'expectedCheckoutRoot' => $this->displayPath($this->upstreamRoot),
            ],
            'selection' => [
                'runnerTarget' => self::RUNNER_TARGET,
                'tastyPattern' => self::TASTY_PATTERN,
                'selectedGroups' => [
                    'Tests.Readers.Docx.tests',
                    'Tests.Writers.Docx.tests',
                ],
            ],
            'claim' => 'Static selected DOCX reader/writer source and fixture inventory only; this is not Tasty --list-tests output and not an upstream DOCX runner result.',
            'claimBoundaries' => [
                'doesAssert' => [
                    'hydrated checkout contains the selected DOCX reader/writer source files required by the planned Tasty pattern',
                    'filesystem-level names, stems, and byte sizes for selected DOCX root and writer-golden fixtures',
                    'candidate static source labels extracted from DOCX Haskell test source text',
                ],
                'doesNotAssert' => [
                    'that Cabal, Tasty, or upstream Pandoc tests were executed',
                    'that the static label hints equal final Tasty --list-tests output',
                    'that any selected DOCX reader or writer test passes',
                    'that DOCX package bytes were read, generated, or compared',
                    'full DOCX/OpenXML parity',
                ],
            ],
            'sourcePreflightSummary' => [
                'upstreamRootPresent' => $sourcePreflight['upstreamRootPresent'] ?? false,
                'missingFiles' => $sourcePreflight['missingFiles'] ?? [],
                'missingDirectories' => $sourcePreflight['missingDirectories'] ?? [],
            ],
        ];

        if (!$ready) {
            $inventory = $base + [
                'skipped' => true,
                'reason' => 'Required DOCX runner source paths or fixture directories are missing.',
                'sourceGroups' => [],
                'fixtures' => $this->emptyFixtureInventory(),
                'staticCandidateTestLabelCount' => 0,
            ];
            $inventory['inventorySha256'] = hash('sha256', self::canonicalJson($inventory));

            return $inventory;
        }

        $sourceGroups = [
            $this->sourceGroupInventory(
                'reader',
                'Tests.Readers.Docx',
                'Tests.Readers.Docx.tests',
                'test/Tests/Readers/Docx.hs'
            ),
            $this->sourceGroupInventory(
                'writer',
                'Tests.Writers.Docx',
                'Tests.Writers.Docx.tests',
                'test/Tests/Writers/Docx.hs'
            ),
        ];
        $staticCandidateTestLabelCount = array_sum(array_map(
            static fn (array $group): int => count($group['candidateStaticLabels'] ?? []),
            $sourceGroups
        ));

        $inventory = $base + [
            'skipped' => false,
            'sourceGroups' => $sourceGroups,
            'fixtures' => $this->fixtureInventory(),
            'staticCandidateTestLabelCount' => $staticCandidateTestLabelCount,
            'staticExtractionLimits' => [
                'candidateStaticLabels are quoted string literals found on DOCX-relevant Haskell source lines',
                'dynamic Tasty names, helper-generated cases, and conditional tests still require the future --list-tests runner artifact',
                'DOCX package files are inventoried by filename and filesystem size only; package bytes are not read or hashed',
            ],
        ];
        $inventory['inventorySha256'] = hash('sha256', self::canonicalJson($inventory));

        return $inventory;
    }

    /**
     * @return array<string, mixed>
     */
    private function sourceGroupInventory(string $kind, string $module, string $entryPoint, string $sourceFile): array
    {
        $path = $this->upstreamPath($sourceFile);
        $contents = is_file($path) ? file_get_contents($path) : false;
        if ($contents === false) {
            return [
                'kind' => $kind,
                'module' => $module,
                'entryPointSnippet' => $entryPoint,
                'sourceFile' => $sourceFile,
                'present' => false,
                'sourceBytes' => 0,
                'sourceSha256' => null,
                'candidateStaticLabels' => [],
            ];
        }

        return [
            'kind' => $kind,
            'module' => $module,
            'entryPointSnippet' => $entryPoint,
            'sourceFile' => $sourceFile,
            'present' => true,
            'sourceBytes' => strlen($contents),
            'sourceSha256' => hash('sha256', $contents),
            'candidateStaticLabels' => self::candidateStaticLabels($contents),
        ];
    }

    /**
     * @return list<array{label:string,line:int}>
     */
    private static function candidateStaticLabels(string $contents): array
    {
        $labels = [];
        $seen = [];
        $lines = preg_split('/\R/', $contents);
        if (!is_array($lines)) {
            return [];
        }

        foreach ($lines as $index => $line) {
            if (preg_match('/\b(?:testGroup|testCase|test|golden|Golden|docx|Docx|native|Native)\b/', $line) !== 1) {
                continue;
            }
            if (preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"/', $line, $matches) !== false) {
                foreach ($matches[1] as $label) {
                    if ($label === '' || isset($seen[$label])) {
                        continue;
                    }
                    $seen[$label] = true;
                    $labels[] = [
                        'label' => $label,
                        'line' => $index + 1,
                    ];
                }
            }
        }

        return $labels;
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureInventory(): array
    {
        $rootDocxPackages = $this->directFileRows($this->upstreamPath('test/docx'), 'test/docx', 'docx');
        $rootNativeExpectations = $this->directFileRows($this->upstreamPath('test/docx'), 'test/docx', 'native');
        $goldenDocxPackages = $this->directFileRows($this->upstreamPath('test/docx/golden'), 'test/docx/golden', 'docx');
        $rootDocxStems = array_column($rootDocxPackages, 'stem');
        $rootNativeStems = array_column($rootNativeExpectations, 'stem');
        $pairedStems = array_values(array_intersect($rootDocxStems, $rootNativeStems));
        $unpairedDocxStems = array_values(array_diff($rootDocxStems, $rootNativeStems));
        $unpairedNativeStems = array_values(array_diff($rootNativeStems, $rootDocxStems));
        sort($pairedStems);
        sort($unpairedDocxStems);
        sort($unpairedNativeStems);

        return [
            'counts' => [
                'rootDocxPackageFiles' => count($rootDocxPackages),
                'rootNativeExpectedFiles' => count($rootNativeExpectations),
                'pairedRootDocxNativeStems' => count($pairedStems),
                'unpairedRootDocxPackageStems' => count($unpairedDocxStems),
                'unpairedRootNativeExpectedStems' => count($unpairedNativeStems),
                'goldenDocxPackageFiles' => count($goldenDocxPackages),
            ],
            'pairedRootDocxNativeStems' => $pairedStems,
            'unpairedRootDocxPackageStems' => $unpairedDocxStems,
            'unpairedRootNativeExpectedStems' => $unpairedNativeStems,
            'rootDocxPackages' => $rootDocxPackages,
            'rootNativeExpectations' => $rootNativeExpectations,
            'goldenDocxPackages' => $goldenDocxPackages,
            'packageBytePolicy' => 'filesystem names and byte sizes only; DOCX package bytes are not read or hashed',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyFixtureInventory(): array
    {
        return [
            'counts' => [
                'rootDocxPackageFiles' => 0,
                'rootNativeExpectedFiles' => 0,
                'pairedRootDocxNativeStems' => 0,
                'unpairedRootDocxPackageStems' => 0,
                'unpairedRootNativeExpectedStems' => 0,
                'goldenDocxPackageFiles' => 0,
            ],
            'pairedRootDocxNativeStems' => [],
            'unpairedRootDocxPackageStems' => [],
            'unpairedRootNativeExpectedStems' => [],
            'rootDocxPackages' => [],
            'rootNativeExpectations' => [],
            'goldenDocxPackages' => [],
            'packageBytePolicy' => 'filesystem names and byte sizes only; DOCX package bytes are not read or hashed',
        ];
    }

    /**
     * @return list<array{path:string,fileName:string,stem:string,extension:string,bytes:int}>
     */
    private function directFileRows(string $directory, string $relativeDirectory, string $extension): array
    {
        if (!is_dir($directory)) {
            return [];
        }

        $rows = [];
        foreach (new \DirectoryIterator($directory) as $entry) {
            if ($entry->isDot() || !$entry->isFile() || strtolower($entry->getExtension()) !== $extension) {
                continue;
            }

            $fileName = $entry->getFilename();
            $rows[] = [
                'path' => $relativeDirectory . '/' . $fileName,
                'fileName' => $fileName,
                'stem' => pathinfo($fileName, PATHINFO_FILENAME),
                'extension' => $extension,
                'bytes' => $entry->getSize(),
            ];
        }

        usort(
            $rows,
            static fn (array $left, array $right): int => $left['path'] <=> $right['path']
        );

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function workspace(): array
    {
        return [
            'root' => '.port-libs/pandoc-runner',
            'directories' => [
                'dependencyBuild' => '.port-libs/pandoc-runner/cabal-build/runner-test-dependencies',
                'targetedRunBuild' => '.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                'logs' => '.port-libs/pandoc-runner/logs',
                'artifacts' => '.port-libs/pandoc-runner/artifacts/docx-targeted-run',
                'tmp' => '.port-libs/pandoc-runner/tmp',
            ],
            'environmentVariables' => self::WORKSPACE_ENVIRONMENT,
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function commands(): array
    {
        return [
            'dependencyDryRun' => $this->commandDescriptor(
                'dependencyDryRun',
                [
                    'v2-build',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=.port-libs/pandoc-runner/cabal-build/runner-test-dependencies',
                    '--dry-run',
                    '--only-dependencies',
                    '--enable-tests',
                    '--disable-benchmarks',
                    'test:test-pandoc',
                    'test:test-pandoc-lua-engine',
                ],
                '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
                'non-mutating dependency plan only; record transcript before any targeted run'
            ),
            'listDocxTests' => $this->commandDescriptor(
                'listDocxTests',
                [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                    self::RUNNER_TARGET,
                    '--',
                    '--list-tests',
                    '--pattern',
                    self::TASTY_PATTERN,
                ],
                '.port-libs/pandoc-runner/logs/docx-targeted-list-tests.txt',
                'future inventory command only; run after reviewed dependency dry-run'
            ),
            'targetedDocxRun' => $this->commandDescriptor(
                'targetedDocxRun',
                [
                    'v2-run',
                    '--offline',
                    '--project-dir=.',
                    '--builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                    self::RUNNER_TARGET,
                    '--',
                    '--pattern',
                    self::TASTY_PATTERN,
                ],
                '.port-libs/pandoc-runner/logs/docx-targeted-run.txt',
                'future targeted runner only; not executed by this PHP preflight'
            ),
        ];
    }

    /**
     * @param list<string> $arguments
     * @return array<string, mixed>
     */
    private function commandDescriptor(string $name, array $arguments, string $transcriptFile, string $executionPolicy): array
    {
        return [
            'name' => $name,
            'program' => 'cabal',
            'arguments' => $arguments,
            'commandLine' => self::commandLine(array_merge(['cabal'], $arguments)),
            'workingDirectory' => $this->displayPath($this->upstreamRoot),
            'environmentVariables' => self::WORKSPACE_ENVIRONMENT,
            'transcriptFile' => $transcriptFile,
            'executionPolicy' => $executionPolicy,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resultArtifactContract(): array
    {
        return [
            'requiredBeforeResultRecorded' => [
                '.port-libs/pandoc-runner/logs/runner-test-dependencies.txt',
                '.port-libs/pandoc-runner/logs/docx-targeted-list-tests.txt',
                '.port-libs/pandoc-runner/logs/docx-targeted-run.txt',
                '.port-libs/pandoc-runner/artifacts/docx-targeted-run/result.json',
            ],
            'resultJsonRequiredFields' => [
                'upstreamCommit',
                'commandLine',
                'exitCode',
                'runnerTarget',
                'tastyPattern',
                'selectedTestCount',
                'passedCount',
                'failedCount',
                'skippedCount',
                'startedAtUtc',
                'finishedAtUtc',
            ],
            'admissionRule' => 'Do not set resultRecorded=true until command transcript, exit code, selected test inventory, and pass/fail counts are captured for the pinned upstream checkout.',
        ];
    }

    /**
     * @return list<string>
     */
    private function activationGate(bool $ready): array
    {
        if (!$ready) {
            return [
                'hydrate Pandoc upstream checkout at ' . self::PINNED_UPSTREAM_COMMIT,
                'restore required DOCX Tasty source files and fixture directories',
                'rerun this preflight tool before executing Cabal commands',
            ];
        }

        return [
            'record dependencyDryRun transcript first',
            'record listDocxTests transcript before the targeted run',
            'record targetedDocxRun transcript and result.json before claiming any upstream DOCX runner result',
        ];
    }

    private function absolutePath(string $path): string
    {
        if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
            return rtrim($path, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    private function upstreamPath(string $relativePath): string
    {
        return $this->upstreamRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    }

    private function displayPath(string $path): string
    {
        $root = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return $path;
    }

    private function hasNonEmptyFile(string $path): bool
    {
        if (!is_file($path)) {
            return false;
        }

        $size = filesize($path);

        return $size !== false && $size > 0;
    }

    private function countDirectFiles(string $directory, string $extension): int
    {
        if (!is_dir($directory)) {
            return 0;
        }

        $count = 0;
        foreach (new \DirectoryIterator($directory) as $entry) {
            if (!$entry->isDot() && $entry->isFile() && strtolower($entry->getExtension()) === $extension) {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @param list<string> $parts
     */
    private static function commandLine(array $parts): string
    {
        return implode(' ', array_map(self::shellArgument(...), $parts));
    }

    private static function canonicalJson(mixed $value): string
    {
        return json_encode(
            self::sortForJson($value),
            JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        );
    }

    private static function sortForJson(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(self::sortForJson(...), $value);
        }

        ksort($value);
        foreach ($value as $key => $item) {
            $value[$key] = self::sortForJson($item);
        }

        return $value;
    }

    private static function shellArgument(string $value): string
    {
        if (preg_match('/^[A-Za-z0-9_.,:\/=@%+-]+$/', $value) === 1) {
            return $value;
        }

        return escapeshellarg($value);
    }

    private static function boolText(mixed $value): string
    {
        return $value === true ? 'yes' : 'no';
    }
}
