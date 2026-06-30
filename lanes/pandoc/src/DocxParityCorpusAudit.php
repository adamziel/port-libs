<?php

declare(strict_types=1);

namespace PortLibs\Pandoc;

final class DocxParityCorpusAudit
{
    public const DEFAULT_RELATIVE_DOCX_DIR = '.upstream-cache/pandoc-current/test/docx';
    public const STATUS_REPORTED = 'reported_local_upstream_docx_native_pair_parse_coverage';
    public const STATUS_SKIPPED_MISSING_SOURCE = 'skipped_missing_upstream_docx_directory';
    public const STATUS_SKIPPED_UNREADABLE_SOURCE = 'skipped_unreadable_upstream_docx_directory';
    public const VERDICT = 'audit-only-not-full-docx-parity';
    public const CLAIM = 'Reports local parser acceptance for paired root-level upstream DOCX/native fixtures plus writer-golden inventory/support status; no AST equality, generated writer package comparison, or upstream Haskell runner parity is asserted.';
    public const GAP_STATUS_OPEN = 'open';
    public const GAP_STATUS_NOT_EVALUATED = 'not-evaluated';
    public const DOCX_RUNNER_PLAN_STATUS = 'open-no-targeted-runner-result';
    public const PARSER_ACCEPTANCE_BASELINE_NAME = 'local-upstream-docx-parser-acceptance-20260630';
    public const PARSER_ACCEPTANCE_BASELINE_PAIRED_ARTIFACTS = 74;
    public const PARSER_ACCEPTANCE_BASELINE_DOCX_PARSED = 74;
    public const PARSER_ACCEPTANCE_BASELINE_NATIVE_PARSED = 74;
    public const PARSER_ACCEPTANCE_BASELINE_BOTH_PARSED = 74;

    private readonly string $repoRoot;
    private readonly string $docxDirectory;

    public function __construct(string $repoRoot, string $docxDirectory = self::DEFAULT_RELATIVE_DOCX_DIR, private readonly int $sampleLimit = 10)
    {
        if ($repoRoot === '') {
            throw new \InvalidArgumentException('Repository root must not be empty');
        }
        if ($docxDirectory === '') {
            throw new \InvalidArgumentException('DOCX directory must not be empty');
        }
        if ($sampleLimit < 0) {
            throw new \InvalidArgumentException('Sample limit must not be negative');
        }

        $this->repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
        $this->docxDirectory = $docxDirectory;
    }

    /**
     * @return array<string, mixed>
     */
    public function report(?int $maxPairs = null): array
    {
        if ($maxPairs !== null && $maxPairs < 0) {
            throw new \InvalidArgumentException('Pair audit limit must not be negative');
        }

        $docxDir = $this->absoluteDocxDirectory();
        if (!is_dir($docxDir)) {
            return $this->skipReport(self::STATUS_SKIPPED_MISSING_SOURCE, "Upstream DOCX directory does not exist: {$docxDir}");
        }
        if (!is_readable($docxDir)) {
            return $this->skipReport(self::STATUS_SKIPPED_UNREADABLE_SOURCE, "Upstream DOCX directory is not readable: {$docxDir}");
        }

        try {
            $inventory = $this->inventory($docxDir);
        } catch (\UnexpectedValueException $exception) {
            return $this->skipReport(self::STATUS_SKIPPED_UNREADABLE_SOURCE, $exception->getMessage());
        }

        $writerGoldenEvidence = (new DocxWriterGoldenManifest($this->repoRoot, $this->docxDirectory, $this->sampleLimit))->report();
        $pairNames = $inventory['pairNames'];
        $auditedPairNames = $maxPairs === null ? $pairNames : array_slice($pairNames, 0, $maxPairs);
        $rows = [];
        $failureRows = [];
        $docxParsed = 0;
        $nativeParsed = 0;
        $bothParsed = 0;

        foreach ($auditedPairNames as $name) {
            $docxResult = $this->parseDocx($inventory['docxByStem'][$name]);
            $nativeResult = $this->parseNative($inventory['nativeByStem'][$name]);
            $docxAccepted = $docxResult['status'] === 'parsed';
            $nativeAccepted = $nativeResult['status'] === 'parsed';
            $acceptedByBoth = $docxAccepted && $nativeAccepted;

            if ($docxAccepted) {
                ++$docxParsed;
            }
            if ($nativeAccepted) {
                ++$nativeParsed;
            }
            if ($acceptedByBoth) {
                ++$bothParsed;
            }

            $row = [
                'name' => $name,
                'docxFile' => $this->displayPath($inventory['docxByStem'][$name]),
                'nativeFile' => $this->displayPath($inventory['nativeByStem'][$name]),
                'docxParse' => $docxResult,
                'nativeParse' => $nativeResult,
                'acceptedByBothParsers' => $acceptedByBoth,
            ];
            $rows[] = $row;

            if (!$acceptedByBoth && count($failureRows) < $this->sampleLimit) {
                $failureRows[] = $row;
            }
        }

        $auditedCount = count($auditedPairNames);
        $docxFailed = $auditedCount - $docxParsed;
        $nativeFailed = $auditedCount - $nativeParsed;

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-parity-audit',
            'status' => self::STATUS_REPORTED,
            'skipped' => false,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'parser-acceptance-only',
            'verificationScope' => self::verificationScope(),
            'upstreamDocxRunnerEvidencePlan' => self::upstreamDocxRunnerEvidencePlan(),
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $docxDir,
            'upstreamDocxDirectoryDisplay' => $this->displayPath($docxDir),
            'sourceDirectoryPresent' => true,
            'rootDirectoryArtifactCount' => $inventory['rootDirectoryArtifactCount'],
            'rootDocxPackageArtifacts' => count($inventory['docxByStem']),
            'rootNativeExpectedArtifacts' => count($inventory['nativeByStem']),
            'goldenDocxPackageArtifacts' => $inventory['goldenDocxPackageArtifacts'],
            'writerGoldenEvidence' => $writerGoldenEvidence,
            'writerGoldenEvidenceKind' => $writerGoldenEvidence['evidenceKind'] ?? DocxWriterGoldenManifest::EVIDENCE_KIND,
            'docxWriterUnsupportedReason' => $writerGoldenEvidence['localWriter']['unsupportedReason'] ?? DocxWriterGoldenManifest::OPEN_REASON,
            'writerGoldenPackageComparisonRun' => $writerGoldenEvidence['packageComparison']['run'] ?? false,
            'pairedDocxNativeArtifacts' => count($pairNames),
            'unpairedDocxPackageArtifacts' => count($inventory['docxWithoutNative']),
            'unpairedNativeExpectedArtifacts' => count($inventory['nativeWithoutDocx']),
            'docxWithoutNativeSamples' => array_slice($inventory['docxWithoutNative'], 0, $this->sampleLimit),
            'nativeWithoutDocxSamples' => array_slice($inventory['nativeWithoutDocx'], 0, $this->sampleLimit),
            'pairAuditLimit' => $maxPairs,
            'auditedPairCount' => $auditedCount,
            'unauditedPairCount' => count($pairNames) - $auditedCount,
            'docxParsedCount' => $docxParsed,
            'docxFailedCount' => $docxFailed,
            'nativeParsedCount' => $nativeParsed,
            'nativeFailedCount' => $nativeFailed,
            'bothParsedCount' => $bothParsed,
            'bothFailedOrPartialCount' => $auditedCount - $bothParsed,
            'docxParseCoveragePercent' => self::percent($docxParsed, $auditedCount),
            'nativeParseCoveragePercent' => self::percent($nativeParsed, $auditedCount),
            'bothParserCoveragePercent' => self::percent($bothParsed, $auditedCount),
            'parseCoverageDenominator' => 'audited paired root-level .docx/.native fixture stems',
            'parserAcceptanceBaseline' => self::parserAcceptanceBaseline(),
            'parserAcceptanceRegression' => self::parserAcceptanceRegression(
                true,
                $maxPairs,
                count($pairNames),
                $auditedCount,
                $docxParsed,
                $docxFailed,
                $nativeParsed,
                $nativeFailed,
                $bothParsed,
                $auditedCount - $bothParsed
            ),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                true,
                count($pairNames),
                $auditedCount,
                $docxFailed,
                $nativeFailed,
                $auditedCount - $bothParsed,
                $inventory['goldenDocxPackageArtifacts'],
                $writerGoldenEvidence
            ),
            'pairRows' => $rows,
            'failureRows' => $failureRows,
            'notes' => [
                'Root-level .docx/.native stem pairs are reader fixtures; golden/*.docx files are counted as writer package inventory only.',
                'The writer-golden manifest records package names and SHA-256 part hashes only; it does not generate PHP DOCX output.',
                'Parser acceptance means the local PHP reader returned an AST node without throwing; it is not an equality comparison against Pandoc output.',
                'This audit does not execute upstream Haskell/Cabal tests, compare generated DOCX packages, or claim full DOCX/OpenXML parity.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function formatTextReport(array $report): string
    {
        $lines = [
            'Pandoc DOCX parity corpus audit',
            'Status: ' . (string) ($report['status'] ?? 'unknown'),
            'Verdict: ' . (string) ($report['verdict'] ?? self::VERDICT),
            'Claim: ' . (string) ($report['claim'] ?? self::CLAIM),
            'Upstream DOCX directory: ' . (string) ($report['upstreamDocxDirectoryDisplay'] ?? $report['upstreamDocxDirectory'] ?? ''),
        ];

        if (($report['skipped'] ?? false) === true) {
            $lines[] = 'Result: skipped';
            $lines[] = 'Reason: ' . (string) ($report['reason'] ?? 'source directory unavailable');
            $lines[] = 'No parser audit was run. This is expected on CI jobs without .upstream-cache.';
            $lines = self::appendWriterGoldenSummary($lines, $report);
            $lines[] = 'No DOCX parity is asserted.';
            $lines = self::appendUpstreamDocxRunnerEvidencePlan($lines, $report);
            $lines = self::appendOrderedRemainingGaps($lines, $report);

            return implode(PHP_EOL, $lines) . PHP_EOL;
        }

        $audited = (int) ($report['auditedPairCount'] ?? 0);
        $lines[] = 'Root artifacts: '
            . (int) ($report['rootDocxPackageArtifacts'] ?? 0)
            . ' .docx, '
            . (int) ($report['rootNativeExpectedArtifacts'] ?? 0)
            . ' .native, '
            . (int) ($report['pairedDocxNativeArtifacts'] ?? 0)
            . ' paired stems';
        $lines[] = 'Golden DOCX artifacts: '
            . (int) ($report['goldenDocxPackageArtifacts'] ?? 0)
            . ' counted as writer inventory only';
        $lines = self::appendWriterGoldenSummary($lines, $report);
        $lines[] = 'Audited pairs: ' . $audited
            . ((int) ($report['unauditedPairCount'] ?? 0) > 0
                ? ' (' . (int) $report['unauditedPairCount'] . ' not audited by limit)'
                : '');
        $lines[] = 'DOCX parser accepted: '
            . (int) ($report['docxParsedCount'] ?? 0)
            . '/'
            . $audited
            . ' ('
            . self::formatPercent($report['docxParseCoveragePercent'] ?? null)
            . ')';
        $lines[] = 'Native parser accepted: '
            . (int) ($report['nativeParsedCount'] ?? 0)
            . '/'
            . $audited
            . ' ('
            . self::formatPercent($report['nativeParseCoveragePercent'] ?? null)
            . ')';
        $lines[] = 'Both parsers accepted: '
            . (int) ($report['bothParsedCount'] ?? 0)
            . '/'
            . $audited
            . ' ('
            . self::formatPercent($report['bothParserCoveragePercent'] ?? null)
            . ')';

        $regression = $report['parserAcceptanceRegression'] ?? null;
        if (is_array($regression)) {
            $lines[] = 'Parser acceptance regression guard: '
                . self::formatRegressionGuard($regression);
        }

        $docxSamples = $report['docxWithoutNativeSamples'] ?? [];
        if (is_array($docxSamples) && $docxSamples !== []) {
            $lines[] = 'Unpaired DOCX samples: ' . implode(', ', array_map('strval', $docxSamples));
        }
        $nativeSamples = $report['nativeWithoutDocxSamples'] ?? [];
        if (is_array($nativeSamples) && $nativeSamples !== []) {
            $lines[] = 'Unpaired native samples: ' . implode(', ', array_map('strval', $nativeSamples));
        }

        $failureRows = $report['failureRows'] ?? [];
        if (is_array($failureRows) && $failureRows !== []) {
            $lines[] = 'Parser failure samples:';
            foreach ($failureRows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $lines[] = '- ' . self::formatFailureRow($row);
            }
        }

        $lines = self::appendUpstreamDocxRunnerEvidencePlan($lines, $report);
        $lines = self::appendOrderedRemainingGaps($lines, $report);
        $lines[] = 'No AST equality, upstream Haskell runner, or DOCX writer golden package parity is asserted.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array<string, mixed>
     */
    private function skipReport(string $status, string $reason): array
    {
        $writerGoldenEvidence = (new DocxWriterGoldenManifest($this->repoRoot, $this->docxDirectory, $this->sampleLimit))->report();

        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-parity-audit',
            'status' => $status,
            'skipped' => true,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'parser-acceptance-only',
            'verificationScope' => self::verificationScope(),
            'upstreamDocxRunnerEvidencePlan' => self::upstreamDocxRunnerEvidencePlan(),
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $this->absoluteDocxDirectory(),
            'upstreamDocxDirectoryDisplay' => $this->displayPath($this->absoluteDocxDirectory()),
            'sourceDirectoryPresent' => false,
            'reason' => $reason,
            'rootDirectoryArtifactCount' => 0,
            'rootDocxPackageArtifacts' => 0,
            'rootNativeExpectedArtifacts' => 0,
            'goldenDocxPackageArtifacts' => 0,
            'writerGoldenEvidence' => $writerGoldenEvidence,
            'writerGoldenEvidenceKind' => $writerGoldenEvidence['evidenceKind'] ?? DocxWriterGoldenManifest::EVIDENCE_KIND,
            'docxWriterUnsupportedReason' => $writerGoldenEvidence['localWriter']['unsupportedReason'] ?? DocxWriterGoldenManifest::OPEN_REASON,
            'writerGoldenPackageComparisonRun' => $writerGoldenEvidence['packageComparison']['run'] ?? false,
            'pairedDocxNativeArtifacts' => 0,
            'unpairedDocxPackageArtifacts' => 0,
            'unpairedNativeExpectedArtifacts' => 0,
            'pairAuditLimit' => null,
            'auditedPairCount' => 0,
            'unauditedPairCount' => 0,
            'docxParsedCount' => 0,
            'docxFailedCount' => 0,
            'nativeParsedCount' => 0,
            'nativeFailedCount' => 0,
            'bothParsedCount' => 0,
            'bothFailedOrPartialCount' => 0,
            'docxParseCoveragePercent' => null,
            'nativeParseCoveragePercent' => null,
            'bothParserCoveragePercent' => null,
            'parserAcceptanceBaseline' => self::parserAcceptanceBaseline(),
            'parserAcceptanceRegression' => self::parserAcceptanceRegression(false, null, 0, 0, 0, 0, 0, 0, 0, 0),
            'orderedRemainingGaps' => self::orderedRemainingGaps(
                false,
                0,
                0,
                0,
                0,
                0,
                0,
                $writerGoldenEvidence
            ),
            'pairRows' => [],
            'failureRows' => [],
            'notes' => [
                'The local upstream DOCX corpus cache is optional for CI.',
                'No parser coverage or parity result is inferred when the source directory is absent.',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $report
     */
    public static function hasParserAcceptanceRegression(array $report): bool
    {
        $regression = $report['parserAcceptanceRegression'] ?? null;

        return is_array($regression)
            && ($regression['evaluated'] ?? false) === true
            && ($regression['regressed'] ?? false) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public static function upstreamDocxRunnerEvidencePlan(): array
    {
        $dryRunDescriptor = UpstreamRunnerDependencyAudit::expectedCabalPlanCommands()['runner-test-dependencies'];
        $workspace = UpstreamRunnerDependencyAudit::expectedCabalPlanWorkspace();
        $futureTargetedDescriptor = [
            'program' => 'cabal',
            'arguments' => [
                'v2-run',
                '--offline',
                '--project-dir=.',
                '--builddir=.port-libs/pandoc-runner/cabal-build/docx-targeted-run',
                'test:test-pandoc',
                '--',
                '--pattern',
                '($2 == "Readers" || $2 == "Writers") && $3 == "Docx"',
            ],
        ];

        return [
            'status' => self::DOCX_RUNNER_PLAN_STATUS,
            'evidenceKind' => 'runner-entry-fixture-command-plan-only',
            'resultRecorded' => false,
            'runnerExecuted' => false,
            'upstreamCommit' => UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT,
            'runnerTarget' => 'test:test-pandoc',
            'runnerEntryPoint' => [
                'packageFile' => 'pandoc.cabal',
                'component' => 'test:test-pandoc',
                'type' => 'exitcode-stdio-1.0',
                'mainIs' => 'test-pandoc.hs',
                'sourceDirectory' => 'test',
                'entryFile' => 'test/test-pandoc.hs',
                'entrySemantics' => [
                    'runs from upstream test directory via inDirectory "test"',
                    'runs Tasty defaultMain $ tests fp',
                    'dispatches both DOCX reader and DOCX writer Tasty groups',
                ],
            ],
            'docxReaderEntryPoint' => [
                'module' => 'Tests.Readers.Docx',
                'sourceFile' => 'test/Tests/Readers/Docx.hs',
                'tastyGroup' => 'testGroup "Docx" Tests.Readers.Docx.tests',
                'entryPointSnippet' => 'Tests.Readers.Docx.tests',
            ],
            'docxWriterEntryPoint' => [
                'module' => 'Tests.Writers.Docx',
                'sourceFile' => 'test/Tests/Writers/Docx.hs',
                'tastyGroup' => 'testGroup "Docx" Tests.Writers.Docx.tests',
                'entryPointSnippet' => 'Tests.Writers.Docx.tests',
            ],
            'fixtureClosure' => [
                'source' => 'pandoc.cabal extra-source-files plus test/test-pandoc.hs DOCX Tasty group dispatch',
                'entrySourceFiles' => [
                    'test/test-pandoc.hs',
                    'test/Tests/Readers/Docx.hs',
                    'test/Tests/Writers/Docx.hs',
                ],
                'readerFixtureGlobs' => [
                    'test/docx/*.docx',
                    'test/docx/*.native',
                ],
                'writerGoldenFixtureGlobs' => [
                    'test/docx/golden/*.docx',
                ],
                'pinnedInventoryCounts' => [
                    'docxDirectoryArtifacts' => 233,
                    'nativeExpectedArtifacts' => 112,
                    'docxPackageArtifacts' => 121,
                    'goldenDocxArtifacts' => 38,
                ],
            ],
            'nonMutatingDryRunPlanCommand' => [
                'descriptor' => 'UpstreamRunnerDependencyAudit::expectedCabalPlanCommands()["runner-test-dependencies"]',
                'program' => $dryRunDescriptor['program'],
                'arguments' => $dryRunDescriptor['arguments'],
                'commandLine' => self::shellCommandLine($dryRunDescriptor['program'], $dryRunDescriptor['arguments']),
                'targets' => $dryRunDescriptor['targets'],
                'workingDirectory' => $dryRunDescriptor['workingDirectory'],
                'buildDirectory' => $dryRunDescriptor['buildDirectory'],
                'executionPolicy' => $dryRunDescriptor['executionPolicy'],
                'workspaceEnvironmentVariables' => array_keys($workspace['environmentVariables']),
                'workspaceBuildDirectory' => $workspace['buildDirectories']['runner-test-dependencies'],
                'transcriptFile' => $workspace['transcriptFiles']['runner-test-dependencies'],
                'claim' => 'Descriptor-only Cabal dependency dry-run command; this audit did not execute it.',
            ],
            'futureTargetedRunCommand' => [
                'program' => $futureTargetedDescriptor['program'],
                'arguments' => $futureTargetedDescriptor['arguments'],
                'commandLine' => self::shellCommandLine($futureTargetedDescriptor['program'], $futureTargetedDescriptor['arguments']),
                'workingDirectory' => 'hydrated Pandoc upstream checkout root',
                'executionPolicy' => 'future targeted runner only after reviewed dry-run plan; not executed by this audit',
                'tastyPatternSource' => 'Tasty AWK-like pattern fields from test tree: $1 outer group, $2 reader/writer group, $3 Docx subgroup.',
                'requiredResultArtifact' => 'A future run must record command transcript, exit code, upstream commit, selected test names or --list-tests output, and per-test pass/fail rows before closing upstream-docx-runner-results.',
            ],
            'honestClaim' => 'This records entry points, fixture closure, and commands for a future targeted run only. It is not an upstream DOCX runner result.',
        ];
    }

    private function absoluteDocxDirectory(): string
    {
        if (str_starts_with($this->docxDirectory, DIRECTORY_SEPARATOR)) {
            return rtrim($this->docxDirectory, DIRECTORY_SEPARATOR);
        }

        return $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $this->docxDirectory);
    }

    /**
     * @return array{
     *     rootDirectoryArtifactCount:int,
     *     docxByStem:array<string, string>,
     *     nativeByStem:array<string, string>,
     *     goldenDocxPackageArtifacts:int,
     *     pairNames:list<string>,
     *     docxWithoutNative:list<string>,
     *     nativeWithoutDocx:list<string>
     * }
     */
    private function inventory(string $docxDir): array
    {
        $docxByStem = [];
        $nativeByStem = [];
        $rootArtifacts = 0;

        foreach (new \DirectoryIterator($docxDir) as $entry) {
            if ($entry->isDot() || !$entry->isFile()) {
                continue;
            }

            ++$rootArtifacts;
            $extension = strtolower($entry->getExtension());
            $stem = pathinfo($entry->getFilename(), PATHINFO_FILENAME);
            if ($extension === 'docx') {
                $docxByStem[$stem] = $entry->getPathname();
            } elseif ($extension === 'native') {
                $nativeByStem[$stem] = $entry->getPathname();
            }
        }

        ksort($docxByStem, SORT_STRING);
        ksort($nativeByStem, SORT_STRING);

        $docxNames = array_keys($docxByStem);
        $nativeNames = array_keys($nativeByStem);
        $pairNames = array_values(array_intersect($docxNames, $nativeNames));
        $docxWithoutNative = array_values(array_diff($docxNames, $nativeNames));
        $nativeWithoutDocx = array_values(array_diff($nativeNames, $docxNames));
        sort($pairNames, SORT_STRING);
        sort($docxWithoutNative, SORT_STRING);
        sort($nativeWithoutDocx, SORT_STRING);

        return [
            'rootDirectoryArtifactCount' => $rootArtifacts,
            'docxByStem' => $docxByStem,
            'nativeByStem' => $nativeByStem,
            'goldenDocxPackageArtifacts' => $this->countGoldenDocxArtifacts($docxDir),
            'pairNames' => $pairNames,
            'docxWithoutNative' => $docxWithoutNative,
            'nativeWithoutDocx' => $nativeWithoutDocx,
        ];
    }

    private function countGoldenDocxArtifacts(string $docxDir): int
    {
        $goldenDir = $docxDir . DIRECTORY_SEPARATOR . 'golden';
        if (!is_dir($goldenDir) || !is_readable($goldenDir)) {
            return 0;
        }

        $count = 0;
        foreach (new \DirectoryIterator($goldenDir) as $entry) {
            if (!$entry->isDot() && $entry->isFile() && strtolower($entry->getExtension()) === 'docx') {
                ++$count;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    private function parseDocx(string $path): array
    {
        try {
            $document = (new DocxReader())->readDocxFile($path);

            return [
                'status' => 'parsed',
                'blockCount' => count($document->children),
                'rootType' => $document->type,
            ];
        } catch (\Throwable $throwable) {
            return [
                'status' => 'failed',
                'errorClass' => $throwable::class,
                'message' => self::oneLine($throwable->getMessage()),
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function parseNative(string $path): array
    {
        try {
            $native = file_get_contents($path);
            if (!is_string($native)) {
                throw new \RuntimeException("Unable to read native fixture: {$path}");
            }
            $document = (new NativeReader())->read($native);

            return [
                'status' => 'parsed',
                'blockCount' => count($document->children),
                'rootType' => $document->type,
            ];
        } catch (\Throwable $throwable) {
            return [
                'status' => 'failed',
                'errorClass' => $throwable::class,
                'message' => self::oneLine($throwable->getMessage()),
            ];
        }
    }

    private function displayPath(string $path): string
    {
        $root = $this->repoRoot . DIRECTORY_SEPARATOR;
        if (str_starts_with($path, $root)) {
            return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
        }

        return $path;
    }

    private static function percent(int $numerator, int $denominator): ?float
    {
        if ($denominator === 0) {
            return null;
        }

        return round(($numerator / $denominator) * 100, 2);
    }

    private static function formatPercent(mixed $value): string
    {
        if (!is_int($value) && !is_float($value)) {
            return 'n/a';
        }

        return number_format((float) $value, 2) . '%';
    }

    /**
     * @return array<string, mixed>
     */
    private static function parserAcceptanceBaseline(): array
    {
        return [
            'baselineName' => self::PARSER_ACCEPTANCE_BASELINE_NAME,
            'pairedDocxNativeArtifacts' => self::PARSER_ACCEPTANCE_BASELINE_PAIRED_ARTIFACTS,
            'docxParsedCount' => self::PARSER_ACCEPTANCE_BASELINE_DOCX_PARSED,
            'nativeParsedCount' => self::PARSER_ACCEPTANCE_BASELINE_NATIVE_PARSED,
            'bothParsedCount' => self::PARSER_ACCEPTANCE_BASELINE_BOTH_PARSED,
            'coverageDenominator' => 'paired root-level upstream .docx/.native fixture stems',
            'evidenceKind' => 'parser-acceptance-only',
            'claim' => 'Regression guard for local PHP parser acceptance only; no AST equality, writer golden package, upstream Haskell runner, or full DOCX/OpenXML parity is asserted.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function verificationScope(): array
    {
        return [
            'asserts' => [
                'root-level same-stem upstream .docx/.native fixture inventory counts',
                'local PHP DocxReader parser acceptance for audited .docx fixtures',
                'local PHP NativeReader parser acceptance for audited .native fixtures',
                'strict regression guard against the recorded 74/74 parser-acceptance baseline when the optional cache is present',
                'local DOCX output registry status and expected DocxWriter class/file presence',
                'upstream writer golden package part names and SHA-256 hashes when the optional cache is present',
                'static upstream DOCX reader/writer runner entry point, fixture closure, and descriptor-only dry-run command plan',
            ],
            'doesNotAssert' => [
                'Pandoc AST equality between DOCX reader output and upstream .native expectations',
                'upstream Haskell/Cabal test-pandoc DOCX runner parity',
                'DOCX writer golden package round-trip parity',
                'DOCX writer support merely because upstream golden packages are inventoried',
                'execution of the future targeted DOCX Tasty runner command',
                'full DOCX/OpenXML semantic parity',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private static function orderedRemainingGaps(
        bool $sourceDirectoryPresent,
        int $pairedDocxNativeArtifacts,
        int $auditedPairCount,
        int $docxFailedCount,
        int $nativeFailedCount,
        int $bothFailedOrPartialCount,
        int $goldenDocxPackageArtifacts,
        array $writerGoldenEvidence = []
    ): array {
        $sourceEvidence = $sourceDirectoryPresent
            ? "optional upstream cache present; {$auditedPairCount}/{$pairedDocxNativeArtifacts} paired root-level stems audited for parser acceptance"
            : 'optional upstream DOCX cache absent; no live corpus parser acceptance was measured in this worktree';
        $writer = $writerGoldenEvidence['localWriter'] ?? [];
        $comparison = $writerGoldenEvidence['packageComparison'] ?? [];
        $writerStatus = is_array($writer) ? (string) ($writer['status'] ?? 'unknown') : 'unknown';
        $registryStatus = is_array($writer) ? (string) ($writer['registryStatus'] ?? 'unknown') : 'unknown';
        $writerReason = is_array($comparison)
            ? (string) ($comparison['reason'] ?? DocxWriterGoldenManifest::OPEN_REASON)
            : DocxWriterGoldenManifest::OPEN_REASON;
        $comparisonRun = is_array($comparison) && ($comparison['run'] ?? false) === true ? 'yes' : 'no';

        return [
            [
                'rank' => 1,
                'id' => 'upstream-docx-runner-results',
                'status' => self::GAP_STATUS_OPEN,
                'currentEvidence' => 'No upstream Haskell/Cabal test-pandoc DOCX reader or writer runner result is recorded by this evidence lane. Static evidence now records the test:test-pandoc entry point, DOCX reader/writer Tasty groups, DOCX fixture globs, and the descriptor-only Cabal dry-run command needed before a future targeted run.',
                'evidenceRequired' => 'Record reproducible upstream DOCX reader/writer runner results or a native-PHP equivalent denominator with per-fixture pass/fail rows.',
            ],
            [
                'rank' => 2,
                'id' => 'docx-native-ast-equality',
                'status' => self::GAP_STATUS_OPEN,
                'currentEvidence' => $sourceEvidence . '; AST equality is not compared.',
                'evidenceRequired' => 'Compare local DOCX reader AST output against the paired upstream .native expectation for each fixture and report exact mismatches.',
            ],
            [
                'rank' => 3,
                'id' => 'writer-golden-docx-package-parity',
                'status' => self::GAP_STATUS_OPEN,
                'currentEvidence' => "golden .docx artifacts inventoried as upstream writer outputs: {$goldenDocxPackageArtifacts}; local DOCX writer status={$writerStatus}; docx output registry={$registryStatus}; generated package comparison run={$comparisonRun}; reason={$writerReason}.",
                'evidenceRequired' => 'Generate DOCX output for upstream writer golden cases and compare package parts, relationships, content types, and document XML semantics.',
            ],
            [
                'rank' => 4,
                'id' => 'parser-failure-zero-tolerance',
                'status' => !$sourceDirectoryPresent
                    ? self::GAP_STATUS_NOT_EVALUATED
                    : (($docxFailedCount === 0 && $nativeFailedCount === 0 && $bothFailedOrPartialCount === 0)
                        ? 'covered-by-current-parser-acceptance-evidence'
                        : self::GAP_STATUS_OPEN),
                'currentEvidence' => $sourceDirectoryPresent
                    ? "docx failures={$docxFailedCount}; native failures={$nativeFailedCount}; partial-or-failed pairs={$bothFailedOrPartialCount}"
                    : 'optional upstream DOCX cache absent; parser failure counts are unavailable for this run',
                'evidenceRequired' => 'Keep DOCX and native parser failure counts at zero for the full audited paired corpus before treating parser acceptance as current.',
            ],
            [
                'rank' => 5,
                'id' => 'checked-in-pinned-docx-package-corpus',
                'status' => self::GAP_STATUS_OPEN,
                'currentEvidence' => 'The lane evidence records checked-in current-upstream drift DOCX packages and synthetic ZIP/XML package slices, not a checked-in pinned upstream DOCX package corpus.',
                'evidenceRequired' => 'Check in or otherwise reproducibly hydrate the pinned upstream DOCX package corpus used by parity evidence, with fixture identity and provenance.',
            ],
        ];
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendWriterGoldenSummary(array $lines, array $report): array
    {
        $writerGolden = $report['writerGoldenEvidence'] ?? [];
        if (!is_array($writerGolden)) {
            return $lines;
        }

        $writer = $writerGolden['localWriter'] ?? [];
        if (is_array($writer)) {
            $lines[] = 'DOCX writer implementation: '
                . (string) ($writer['status'] ?? 'unknown')
                . '; classExists='
                . self::formatBool($writer['classExists'] ?? false)
                . '; fileExists='
                . self::formatBool($writer['fileExists'] ?? false)
                . '; registryStatus='
                . (string) ($writer['registryStatus'] ?? 'unknown');
        }

        $comparison = $writerGolden['packageComparison'] ?? [];
        if (is_array($comparison)) {
            $lines[] = 'DOCX writer golden package comparison: '
                . ((($comparison['run'] ?? false) === true) ? 'run' : 'not run')
                . '; reason='
                . (string) ($comparison['reason'] ?? DocxWriterGoldenManifest::OPEN_REASON);
        }

        if (($writerGolden['skipped'] ?? false) !== true) {
            $lines[] = 'Writer golden package parts inventoried: '
                . (int) ($writerGolden['readablePackagePartCount'] ?? 0)
                . '/'
                . (int) ($writerGolden['packagePartCount'] ?? 0)
                . ' hashed readable parts';
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendUpstreamDocxRunnerEvidencePlan(array $lines, array $report): array
    {
        $plan = $report['upstreamDocxRunnerEvidencePlan'] ?? null;
        if (!is_array($plan)) {
            return $lines;
        }

        $reader = is_array($plan['docxReaderEntryPoint'] ?? null) ? $plan['docxReaderEntryPoint'] : [];
        $writer = is_array($plan['docxWriterEntryPoint'] ?? null) ? $plan['docxWriterEntryPoint'] : [];
        $fixtureClosure = is_array($plan['fixtureClosure'] ?? null) ? $plan['fixtureClosure'] : [];
        $dryRun = is_array($plan['nonMutatingDryRunPlanCommand'] ?? null) ? $plan['nonMutatingDryRunPlanCommand'] : [];
        $future = is_array($plan['futureTargetedRunCommand'] ?? null) ? $plan['futureTargetedRunCommand'] : [];

        $lines[] = 'Upstream DOCX runner plan: '
            . (string) ($plan['status'] ?? self::DOCX_RUNNER_PLAN_STATUS)
            . '; result recorded='
            . (($plan['resultRecorded'] ?? false) === true ? 'yes' : 'no')
            . '; runner executed='
            . (($plan['runnerExecuted'] ?? false) === true ? 'yes' : 'no');
        $lines[] = 'DOCX runner entry points: reader '
            . (string) ($reader['sourceFile'] ?? 'test/Tests/Readers/Docx.hs')
            . ' -> '
            . (string) ($reader['entryPointSnippet'] ?? 'Tests.Readers.Docx.tests')
            . '; writer '
            . (string) ($writer['sourceFile'] ?? 'test/Tests/Writers/Docx.hs')
            . ' -> '
            . (string) ($writer['entryPointSnippet'] ?? 'Tests.Writers.Docx.tests');

        $readerGlobs = is_array($fixtureClosure['readerFixtureGlobs'] ?? null) ? $fixtureClosure['readerFixtureGlobs'] : [];
        $writerGlobs = is_array($fixtureClosure['writerGoldenFixtureGlobs'] ?? null) ? $fixtureClosure['writerGoldenFixtureGlobs'] : [];
        $lines[] = 'DOCX fixture closure: '
            . implode(', ', array_merge(array_map('strval', $readerGlobs), array_map('strval', $writerGlobs)));

        if ($dryRun !== []) {
            $lines[] = 'Non-mutating Cabal dry-run plan command: '
                . (string) ($dryRun['commandLine'] ?? '')
                . ' ['
                . (string) ($dryRun['executionPolicy'] ?? '')
                . ']';
        }
        if ($future !== []) {
            $lines[] = 'Future targeted DOCX runner command: '
                . (string) ($future['commandLine'] ?? '')
                . ' ['
                . (string) ($future['executionPolicy'] ?? '')
                . ']';
        }

        return $lines;
    }

    /**
     * @param list<string> $lines
     * @param array<string, mixed> $report
     * @return list<string>
     */
    private static function appendOrderedRemainingGaps(array $lines, array $report): array
    {
        $gaps = $report['orderedRemainingGaps'] ?? [];
        if (!is_array($gaps) || $gaps === []) {
            return $lines;
        }

        $lines[] = 'Ordered remaining full DOCX parity gaps:';
        foreach ($gaps as $gap) {
            if (!is_array($gap)) {
                continue;
            }
            $lines[] = sprintf(
                '%d. %s [%s]: current=%s required=%s',
                (int) ($gap['rank'] ?? 0),
                (string) ($gap['id'] ?? 'unknown-gap'),
                (string) ($gap['status'] ?? 'unknown'),
                (string) ($gap['currentEvidence'] ?? ''),
                (string) ($gap['evidenceRequired'] ?? '')
            );
        }

        return $lines;
    }

    /**
     * @param list<string> $arguments
     */
    private static function shellCommandLine(string $program, array $arguments): string
    {
        $parts = [self::shellArgument($program)];
        foreach ($arguments as $argument) {
            $parts[] = self::shellArgument((string) $argument);
        }

        return implode(' ', $parts);
    }

    private static function shellArgument(string $argument): string
    {
        if ($argument !== '' && preg_match('/^[A-Za-z0-9_\/:.,=@%+~-]+$/', $argument) === 1) {
            return $argument;
        }

        return "'" . str_replace("'", "'\\''", $argument) . "'";
    }

    /**
     * @return array<string, mixed>
     */
    private static function parserAcceptanceRegression(
        bool $sourceDirectoryPresent,
        ?int $pairAuditLimit,
        int $pairedDocxNativeArtifacts,
        int $auditedPairCount,
        int $docxParsedCount,
        int $docxFailedCount,
        int $nativeParsedCount,
        int $nativeFailedCount,
        int $bothParsedCount,
        int $bothFailedOrPartialCount
    ): array {
        $evaluated = $sourceDirectoryPresent
            && ($pairAuditLimit === null || $auditedPairCount >= self::PARSER_ACCEPTANCE_BASELINE_PAIRED_ARTIFACTS);
        $reason = 'evaluated';
        if (!$sourceDirectoryPresent) {
            $reason = 'not-evaluated-source-directory-unavailable';
        } elseif (!$evaluated) {
            $reason = 'not-evaluated-audit-limited-below-baseline';
        }

        $failureReasons = [];
        if ($evaluated) {
            if ($pairedDocxNativeArtifacts < self::PARSER_ACCEPTANCE_BASELINE_PAIRED_ARTIFACTS) {
                $failureReasons[] = 'paired-docx-native-artifact-count-below-baseline';
            }
            if ($auditedPairCount < self::PARSER_ACCEPTANCE_BASELINE_PAIRED_ARTIFACTS) {
                $failureReasons[] = 'audited-pair-count-below-baseline';
            }
            if ($docxParsedCount < self::PARSER_ACCEPTANCE_BASELINE_DOCX_PARSED) {
                $failureReasons[] = 'docx-parser-accepted-count-below-baseline';
            }
            if ($nativeParsedCount < self::PARSER_ACCEPTANCE_BASELINE_NATIVE_PARSED) {
                $failureReasons[] = 'native-parser-accepted-count-below-baseline';
            }
            if ($bothParsedCount < self::PARSER_ACCEPTANCE_BASELINE_BOTH_PARSED) {
                $failureReasons[] = 'both-parser-accepted-count-below-baseline';
            }
            if ($docxFailedCount > 0) {
                $failureReasons[] = 'docx-parse-failures-present';
            }
            if ($nativeFailedCount > 0) {
                $failureReasons[] = 'native-parse-failures-present';
            }
            if ($bothFailedOrPartialCount > 0) {
                $failureReasons[] = 'both-parser-partial-or-failed-pairs-present';
            }
        }

        $passed = $evaluated && $failureReasons === [];

        return [
            'baselineName' => self::PARSER_ACCEPTANCE_BASELINE_NAME,
            'evaluated' => $evaluated,
            'passed' => $passed,
            'regressed' => $evaluated && !$passed,
            'reason' => $reason,
            'failureReasons' => $failureReasons,
            'baselinePairedDocxNativeArtifacts' => self::PARSER_ACCEPTANCE_BASELINE_PAIRED_ARTIFACTS,
            'baselineDocxParsedCount' => self::PARSER_ACCEPTANCE_BASELINE_DOCX_PARSED,
            'baselineNativeParsedCount' => self::PARSER_ACCEPTANCE_BASELINE_NATIVE_PARSED,
            'baselineBothParsedCount' => self::PARSER_ACCEPTANCE_BASELINE_BOTH_PARSED,
            'actualPairedDocxNativeArtifacts' => $pairedDocxNativeArtifacts,
            'actualAuditedPairCount' => $auditedPairCount,
            'actualDocxParsedCount' => $docxParsedCount,
            'actualDocxFailedCount' => $docxFailedCount,
            'actualNativeParsedCount' => $nativeParsedCount,
            'actualNativeFailedCount' => $nativeFailedCount,
            'actualBothParsedCount' => $bothParsedCount,
            'actualBothFailedOrPartialCount' => $bothFailedOrPartialCount,
            'guardrailClaim' => 'Regression guard for parser acceptance only; passing this guard is not DOCX semantic parity.',
        ];
    }

    /**
     * @param array<string, mixed> $regression
     */
    private static function formatRegressionGuard(array $regression): string
    {
        $baseline = (int) ($regression['baselineBothParsedCount'] ?? self::PARSER_ACCEPTANCE_BASELINE_BOTH_PARSED);
        if (($regression['evaluated'] ?? false) !== true) {
            return 'not evaluated ('
                . (string) ($regression['reason'] ?? 'unknown')
                . "; baseline {$baseline}/{$baseline} parser acceptance)";
        }

        $status = (($regression['passed'] ?? false) === true) ? 'passed' : 'failed';
        $actual = (int) ($regression['actualBothParsedCount'] ?? 0);
        $actualDenominator = (int) ($regression['actualAuditedPairCount'] ?? 0);
        $text = "{$status} (actual {$actual}/{$actualDenominator}; baseline {$baseline}/{$baseline} parser acceptance)";
        $failureReasons = $regression['failureReasons'] ?? [];
        if (is_array($failureReasons) && $failureReasons !== []) {
            $text .= '; reasons=' . implode(',', array_map('strval', $failureReasons));
        }

        return $text;
    }

    private static function formatBool(mixed $value): string
    {
        return $value === true ? 'yes' : 'no';
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function formatFailureRow(array $row): string
    {
        $parts = [(string) ($row['name'] ?? 'unknown')];
        foreach (['docxParse' => 'docx', 'nativeParse' => 'native'] as $key => $label) {
            $parse = $row[$key] ?? null;
            if (!is_array($parse) || ($parse['status'] ?? null) !== 'failed') {
                continue;
            }
            $parts[] = $label . ' failed '
                . (string) ($parse['errorClass'] ?? 'Throwable')
                . ': '
                . (string) ($parse['message'] ?? '');
        }

        return implode('; ', $parts);
    }

    private static function oneLine(string $message): string
    {
        $message = preg_replace('/\s+/', ' ', trim($message));
        if (!is_string($message)) {
            return '';
        }

        return strlen($message) > 240 ? substr($message, 0, 237) . '...' : $message;
    }
}
