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
    public const CLAIM = 'Reports local parser acceptance for paired root-level upstream DOCX/native fixtures only; no AST equality, writer golden package, or upstream Haskell runner parity is asserted.';
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
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $docxDir,
            'upstreamDocxDirectoryDisplay' => $this->displayPath($docxDir),
            'sourceDirectoryPresent' => true,
            'rootDirectoryArtifactCount' => $inventory['rootDirectoryArtifactCount'],
            'rootDocxPackageArtifacts' => count($inventory['docxByStem']),
            'rootNativeExpectedArtifacts' => count($inventory['nativeByStem']),
            'goldenDocxPackageArtifacts' => $inventory['goldenDocxPackageArtifacts'],
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
            'pairRows' => $rows,
            'failureRows' => $failureRows,
            'notes' => [
                'Root-level .docx/.native stem pairs are reader fixtures; golden/*.docx files are counted as writer package inventory only.',
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
            $lines[] = 'No DOCX parity is asserted.';

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

        $lines[] = 'No AST equality, upstream Haskell runner, or DOCX writer golden package parity is asserted.';

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array<string, mixed>
     */
    private function skipReport(string $status, string $reason): array
    {
        return [
            'schemaVersion' => 1,
            'tool' => 'pandoc-docx-parity-audit',
            'status' => $status,
            'skipped' => true,
            'verdict' => self::VERDICT,
            'claim' => self::CLAIM,
            'evidenceKind' => 'parser-acceptance-only',
            'repoRoot' => $this->repoRoot,
            'upstreamDocxDirectory' => $this->absoluteDocxDirectory(),
            'upstreamDocxDirectoryDisplay' => $this->displayPath($this->absoluteDocxDirectory()),
            'sourceDirectoryPresent' => false,
            'reason' => $reason,
            'rootDirectoryArtifactCount' => 0,
            'rootDocxPackageArtifacts' => 0,
            'rootNativeExpectedArtifacts' => 0,
            'goldenDocxPackageArtifacts' => 0,
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
