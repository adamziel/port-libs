<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\EpubExecutableNativeAstComparisonHarness;
use PortLibs\Pandoc\EpubNativeAstPackageComparisonHarness;
use PortLibs\Pandoc\EpubReader;
use PortLibs\Pandoc\HtmlNativeAstComparisonHarness;
use PortLibs\Pandoc\HtmlReader;
use PortLibs\Pandoc\MarkdownNativeAstComparisonHarness;
use PortLibs\Pandoc\MarkdownReader;
use PortLibs\Pandoc\NativeReader;
use PortLibs\Pandoc\PptxExecutableNativeAstComparisonHarness;
use PortLibs\Pandoc\PptxNativeAstComparisonHarness;
use PortLibs\Pandoc\PptxReader;

require __DIR__ . '/bootstrap.php';

$repoRoot = dirname(__DIR__);
$limitPerFormat = 20;
$pandocBin = getenv('PANDOC_BIN') ?: 'pandoc';
$outputHtml = $repoRoot . '/lanes/pandoc/reports/haskell-pandoc-sample-comparison.html';
$outputJson = $repoRoot . '/lanes/pandoc/reports/haskell-pandoc-sample-comparison.json';
$manifestPath = null;
$jsonToStdout = false;
$htmlToStdout = false;
$requireMatches = false;

foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help' || $argument === '-h') {
        fwrite(STDOUT, <<<'TXT'
Usage: php tools/pandoc-reader-executable-sample-report.php [--limit-per-format=N] [--manifest=PATH] [--pandoc-bin=PATH] [--output-html=PATH] [--output-json=PATH] [--json] [--html] [--require-matches]

Compares local PHP readers against a Haskell pandoc executable for a fixed-size
sample from the hardened HTML, Markdown, EPUB, and PPTX reader fixture corpora.
The default sample size is 20 documents per format. HTML/Markdown are compared
directly against `pandoc -t native`; EPUB/PPTX reuse the existing executable
native AST comparison harnesses.

With --manifest, compares the exact files listed in a JSON manifest. Manifest
entries can include sourceKind, sourceUrl, description, features, readerOptions,
and pandocFormat metadata for the generated HTML report.

TXT);
        exit(0);
    }

    if ($argument === '--json') {
        $jsonToStdout = true;
        continue;
    }

    if ($argument === '--html') {
        $htmlToStdout = true;
        continue;
    }

    if ($argument === '--require-matches') {
        $requireMatches = true;
        continue;
    }

    if (str_starts_with($argument, '--limit-per-format=')) {
        $rawLimit = substr($argument, strlen('--limit-per-format='));
        if (!ctype_digit($rawLimit)) {
            fwrite(STDERR, "--limit-per-format must be a non-negative integer\n");
            exit(2);
        }
        $limitPerFormat = (int) $rawLimit;
        continue;
    }

    if (str_starts_with($argument, '--pandoc-bin=')) {
        $pandocBin = substr($argument, strlen('--pandoc-bin='));
        if ($pandocBin === '') {
            fwrite(STDERR, "--pandoc-bin must not be empty\n");
            exit(2);
        }
        continue;
    }

    if (str_starts_with($argument, '--manifest=')) {
        $manifestPath = normalizeOutputPath($repoRoot, substr($argument, strlen('--manifest=')));
        continue;
    }

    if (str_starts_with($argument, '--output-html=')) {
        $outputHtml = normalizeOutputPath($repoRoot, substr($argument, strlen('--output-html=')));
        continue;
    }

    if (str_starts_with($argument, '--output-json=')) {
        $outputJson = normalizeOutputPath($repoRoot, substr($argument, strlen('--output-json=')));
        continue;
    }

    fwrite(STDERR, "Unknown argument: {$argument}\n");
    exit(2);
}

$resolvedPandoc = resolveExecutable($pandocBin);
if ($resolvedPandoc === null) {
    fwrite(STDERR, "pandoc executable not found: {$pandocBin}\n");
    exit(1);
}

$report = $manifestPath === null
    ? buildReport($repoRoot, $resolvedPandoc, $limitPerFormat)
    : buildManifestReport($repoRoot, $resolvedPandoc, $manifestPath);
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) . "\n";
$html = renderHtmlReport($report);

writeFileWithDirectory($outputJson, $json);
writeFileWithDirectory($outputHtml, $html);

if ($jsonToStdout) {
    fwrite(STDOUT, $json);
} elseif ($htmlToStdout) {
    fwrite(STDOUT, $html);
} else {
    fwrite(STDOUT, "Wrote {$outputHtml}\n");
    fwrite(STDOUT, "Wrote {$outputJson}\n");
    fwrite(STDOUT, summaryLine($report) . "\n");
}

if ($requireMatches && !reportHasRequiredMatches($report, $manifestPath === null ? $limitPerFormat : null)) {
    fwrite(STDERR, "pandoc-reader-executable-sample-report: sample comparison did not satisfy required matches\n");
    exit(1);
}

exit(0);

/**
 * @return array<string, mixed>
 */
function buildReport(string $repoRoot, string $pandoc, int $limitPerFormat): array
{
    $html = runInlineExecutableComparison(
        'html',
        $repoRoot . '/lanes/pandoc/fixtures',
        'html',
        $limitPerFormat,
        $pandoc
    );
    $markdown = runInlineExecutableComparison(
        'markdown',
        $repoRoot . '/lanes/pandoc/fixtures',
        'md',
        $limitPerFormat,
        $pandoc
    );

    $epubHarness = new EpubExecutableNativeAstComparisonHarness();
    $epub = $epubHarness->run($repoRoot . '/lanes/pandoc/fixtures/upstream-current-epub-reader/epub', [
        'limit' => $limitPerFormat,
        'pandocBin' => $pandoc,
    ]);

    $pptxHarness = new PptxExecutableNativeAstComparisonHarness();
    $pptx = $pptxHarness->run($repoRoot . '/lanes/pandoc/fixtures/upstream-current-pptx-reader', [
        'limit' => $limitPerFormat,
        'pandocBin' => $pandoc,
    ]);

    $formats = [
        'html' => summarizeInlineFormat($html),
        'markdown' => summarizeInlineFormat($markdown),
        'epub' => summarizeExecutableHarnessFormat($epub, 'epub'),
        'pptx' => summarizeExecutableHarnessFormat($pptx, 'pptx'),
    ];

    return [
        'schemaVersion' => 1,
        'tool' => 'pandoc-reader-executable-sample-report',
        'generatedAt' => gmdate('c'),
        'repoRoot' => $repoRoot,
        'pandocExecutable' => $pandoc,
        'pandocVersion' => pandocVersion($pandoc),
        'limitPerFormat' => $limitPerFormat,
        'formats' => $formats,
        'rawReports' => [
            'html' => $html,
            'markdown' => $markdown,
            'epub' => compactExecutableHarnessReport($epub),
            'pptx' => compactExecutableHarnessReport($pptx),
        ],
        'status' => formatsHaveRequiredMatches($formats, $limitPerFormat) ? 'passed' : 'failed',
    ];
}

/**
 * @return array<string, mixed>
 */
function buildManifestReport(string $repoRoot, string $pandoc, string $manifestPath): array
{
    if (!is_file($manifestPath)) {
        throw new RuntimeException("Manifest not found: {$manifestPath}");
    }
    $manifest = json_decode((string) file_get_contents($manifestPath), true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($manifest)) {
        throw new RuntimeException("Manifest must decode to an object: {$manifestPath}");
    }
    $entries = $manifest['entries'] ?? null;
    if (!is_array($entries)) {
        throw new RuntimeException("Manifest must contain an entries array: {$manifestPath}");
    }

    $rowsByFormat = [];
    foreach ($entries as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $row = runManifestEntry($repoRoot, $pandoc, $entry);
        $rowsByFormat[(string) ($row['format'] ?? 'unknown')][] = $row;
    }

    $formats = [];
    foreach ($rowsByFormat as $format => $rows) {
        $formats[$format] = summarizeManifestFormat($rows);
    }
    ksort($formats, SORT_STRING);

    return [
        'schemaVersion' => 1,
        'tool' => 'pandoc-reader-executable-sample-report',
        'mode' => 'manifest',
        'title' => (string) ($manifest['title'] ?? 'Diverse Haskell Pandoc Reader Comparison'),
        'description' => (string) ($manifest['description'] ?? ''),
        'generatedAt' => gmdate('c'),
        'repoRoot' => $repoRoot,
        'manifestPath' => $manifestPath,
        'pandocExecutable' => $pandoc,
        'pandocVersion' => pandocVersion($pandoc),
        'limitPerFormat' => null,
        'formats' => $formats,
        'rawReports' => $formats,
        'status' => formatsHaveRequiredMatches($formats, null) ? 'passed' : 'failed',
    ];
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function runManifestEntry(string $repoRoot, string $pandoc, array $entry): array
{
    $format = (string) ($entry['format'] ?? '');
    if (!in_array($format, ['html', 'markdown', 'epub', 'pptx'], true)) {
        throw new RuntimeException("Unsupported manifest format: {$format}");
    }
    $path = normalizeOutputPath($repoRoot, (string) ($entry['path'] ?? ''));
    $readerOptions = is_array($entry['readerOptions'] ?? null) ? $entry['readerOptions'] : [];
    $pandocFormat = (string) (
        $entry['pandocFormat']
        ?? match ($format) {
            'html' => htmlPandocFormat($readerOptions),
            'markdown' => markdownPandocFormat($readerOptions),
            default => $format,
        }
    );
    $label = (string) ($entry['id'] ?? pathinfo($path, PATHINFO_FILENAME));

    $localResult = readLocalManifestFixture($format, $path, $readerOptions);
    $pandocResult = readPandocNative($pandoc, $pandocFormat, $path);

    $row = [
        'fixture' => $label,
        'format' => $format,
        'sourceFile' => relativePath($repoRoot, $path),
        'pandocFormat' => $pandocFormat,
        'readerOptions' => $readerOptions,
        'sourceKind' => (string) ($entry['sourceKind'] ?? 'unknown'),
        'sourceUrl' => isset($entry['sourceUrl']) && is_string($entry['sourceUrl']) ? $entry['sourceUrl'] : null,
        'description' => (string) ($entry['description'] ?? ''),
        'features' => is_array($entry['features'] ?? null) ? array_values(array_map('strval', $entry['features'])) : [],
        'localParsed' => (bool) $localResult['ok'],
        'pandocParsed' => (bool) $pandocResult['ok'],
        'nativeFixtureParsed' => false,
        'localPandocStatus' => 'not-compared',
        'pandocNativeFixtureStatus' => 'not-required',
        'status' => 'parse-failure',
    ];

    if (!$localResult['ok']) {
        $row['localError'] = $localResult['error'];
    }
    if (!$pandocResult['ok']) {
        $row['pandocError'] = $pandocResult['error'];
    }

    if ($localResult['ok'] && $pandocResult['ok']) {
        /** @var AstNode $localDocument */
        $localDocument = $localResult['document'];
        /** @var AstNode $pandocDocument */
        $pandocDocument = $pandocResult['document'];
        $normalizer = normalizerForFormat($format);
        $localAst = $normalizer->normalizedDocument($localDocument);
        $pandocAst = $normalizer->normalizedDocument($pandocDocument);
        if ($localAst === $pandocAst) {
            $row['localPandocStatus'] = 'matched';
            $row['status'] = 'matched';
        } else {
            $row['localPandocStatus'] = 'mismatched';
            $row['status'] = 'mismatched';
            $row['localPandocFirstDifference'] = firstDifference($localAst, $pandocAst) ?? 'unknown-normalized-ast-difference';
        }
    }

    return $row;
}

/**
 * @param list<array<string, mixed>> $rows
 * @return array<string, mixed>
 */
function summarizeManifestFormat(array $rows): array
{
    $compared = count($rows);
    $localParsed = count(array_filter($rows, static fn (array $row): bool => ($row['localParsed'] ?? false) === true));
    $pandocParsed = count(array_filter($rows, static fn (array $row): bool => ($row['pandocParsed'] ?? false) === true));
    $matches = count(array_filter($rows, static fn (array $row): bool => ($row['localPandocStatus'] ?? null) === 'matched'));
    $parseFailures = count(array_filter(
        $rows,
        static fn (array $row): bool => ($row['localParsed'] ?? false) !== true || ($row['pandocParsed'] ?? false) !== true
    ));
    $features = [];
    $sourceKinds = [];
    foreach ($rows as $row) {
        $sourceKinds[(string) ($row['sourceKind'] ?? 'unknown')] = true;
        foreach (($row['features'] ?? []) as $feature) {
            if (is_scalar($feature)) {
                $features[(string) $feature] = true;
            }
        }
    }
    ksort($features, SORT_STRING);
    ksort($sourceKinds, SORT_STRING);

    return [
        'status' => 'completed',
        'totalAvailableCount' => $compared,
        'candidateScannedCount' => $compared,
        'skippedCandidateCount' => 0,
        'comparedCount' => $compared,
        'localParsedCount' => $localParsed,
        'pandocParsedCount' => $pandocParsed,
        'nativeFixtureParsedCount' => 0,
        'parseFailureCount' => $parseFailures,
        'localPandocMatchCount' => $matches,
        'localPandocMismatchCount' => $compared - $matches,
        'pandocNativeFixtureMatchCount' => 0,
        'pandocNativeFixtureMismatchCount' => 0,
        'nativeFixtureRequired' => false,
        'matchPercent' => percent($matches, $compared),
        'result' => $compared > 0 && $parseFailures === 0 && $matches === $compared ? 'passed' : 'failed',
        'features' => array_keys($features),
        'sourceKinds' => array_keys($sourceKinds),
        'fixtureComparisons' => $rows,
        'skippedCandidateComparisons' => [],
    ];
}

/**
 * @return array<string, mixed>
 */
function runInlineExecutableComparison(string $format, string $fixtureDirectory, string $extension, int $limit, string $pandoc): array
{
    if (!is_dir($fixtureDirectory)) {
        return [
            'status' => 'skipped',
            'reason' => 'fixture-directory-missing',
            'fixtureDirectory' => $fixtureDirectory,
            'totalAvailableCount' => 0,
            'comparedCount' => 0,
            'fixtureComparisons' => [],
        ];
    }

    $sourceFiles = filesByBasename($fixtureDirectory, $extension);
    $nativeFiles = filesByBasename($fixtureDirectory, 'native');
    $pairNames = [];
    foreach ($sourceFiles as $basename => $_path) {
        if (!isset($nativeFiles[$basename])) {
            continue;
        }
        if ($format === 'html' && !HtmlNativeAstComparisonHarness::isHtmlReaderFixtureBasename($basename)) {
            continue;
        }
        if ($format === 'markdown' && !MarkdownNativeAstComparisonHarness::isMarkdownReaderFixtureBasename($basename)) {
            continue;
        }
        $pairNames[] = $basename;
    }
    sort($pairNames, SORT_STRING);
    $totalAvailableCount = count($pairNames);
    $fixtureComparisons = [];
    $skippedCandidateComparisons = [];
    $normalizer = $format === 'html'
        ? new HtmlNativeAstComparisonHarness()
        : new MarkdownNativeAstComparisonHarness();
    $nativeReader = new NativeReader();
    $targetCount = $limit > 0 ? $limit : PHP_INT_MAX;

    foreach ($pairNames as $basename) {
        if (count($fixtureComparisons) >= $targetCount) {
            break;
        }

        $sourcePath = $sourceFiles[$basename];
        $nativePath = $nativeFiles[$basename];
        $options = $format === 'html'
            ? HtmlNativeAstComparisonHarness::readerOptionsForFixtureBasename($basename)
            : MarkdownNativeAstComparisonHarness::readerOptionsForFixtureBasename($basename);
        $pandocFormat = $format === 'html' ? htmlPandocFormat($options) : markdownPandocFormat($options);

        $localResult = readLocalInlineFixture($format, $sourcePath, $options);
        $pandocResult = readPandocNative($pandoc, $pandocFormat, $sourcePath);
        $nativeResult = readNativeFixture($nativeReader, $nativePath);

        $row = [
            'fixture' => $basename,
            'sourceFile' => basename($sourcePath),
            'nativeFile' => basename($nativePath),
            'pandocFormat' => $pandocFormat,
            'readerOptions' => $options,
            'localParsed' => (bool) $localResult['ok'],
            'pandocParsed' => (bool) $pandocResult['ok'],
            'nativeFixtureParsed' => (bool) $nativeResult['ok'],
            'localPandocStatus' => 'not-compared',
            'pandocNativeFixtureStatus' => 'not-compared',
            'status' => 'parse-failure',
        ];

        if (!$localResult['ok']) {
            $row['localError'] = $localResult['error'];
        }
        if (!$pandocResult['ok']) {
            $row['pandocError'] = $pandocResult['error'];
        }
        if (!$nativeResult['ok']) {
            $row['nativeFixtureError'] = $nativeResult['error'];
        }

        if ($localResult['ok'] && $pandocResult['ok']) {
            /** @var AstNode $localDocument */
            $localDocument = $localResult['document'];
            /** @var AstNode $pandocDocument */
            $pandocDocument = $pandocResult['document'];
            $localAst = $normalizer->normalizedDocument($localDocument);
            $pandocAst = $normalizer->normalizedDocument($pandocDocument);
            if ($localAst === $pandocAst) {
                $row['localPandocStatus'] = 'matched';
            } else {
                $row['localPandocStatus'] = 'mismatched';
                $row['localPandocFirstDifference'] = firstDifference($localAst, $pandocAst) ?? 'unknown-normalized-ast-difference';
            }
        }

        if ($pandocResult['ok'] && $nativeResult['ok']) {
            /** @var AstNode $nativeDocument */
            $nativeDocument = $nativeResult['document'];
            /** @var AstNode $pandocDocument */
            $pandocDocument = $pandocResult['document'];
            $nativeAst = $normalizer->normalizedDocument($nativeDocument);
            $pandocAst = $normalizer->normalizedDocument($pandocDocument);
            if ($nativeAst === $pandocAst) {
                $row['pandocNativeFixtureStatus'] = 'matched';
            } else {
                $row['pandocNativeFixtureStatus'] = 'mismatched';
                $row['pandocNativeFixtureFirstDifference'] = firstDifference($nativeAst, $pandocAst) ?? 'unknown-native-fixture-difference';
            }
        }

        if ($row['localPandocStatus'] === 'matched' && $row['pandocNativeFixtureStatus'] === 'matched') {
            $row['status'] = 'matched';
        } elseif ($row['localPandocStatus'] === 'mismatched' || $row['pandocNativeFixtureStatus'] === 'mismatched') {
            $row['status'] = 'mismatched';
        }

        if ($row['status'] === 'matched') {
            $fixtureComparisons[] = $row;
        } else {
            $skippedCandidateComparisons[] = $row;
        }
    }

    $comparedCount = count($fixtureComparisons);
    $localParsedCount = count(array_filter($fixtureComparisons, static fn (array $row): bool => ($row['localParsed'] ?? false) === true));
    $pandocParsedCount = count(array_filter($fixtureComparisons, static fn (array $row): bool => ($row['pandocParsed'] ?? false) === true));
    $nativeFixtureParsedCount = count(array_filter($fixtureComparisons, static fn (array $row): bool => ($row['nativeFixtureParsed'] ?? false) === true));
    $bothParsedCount = count(array_filter(
        $fixtureComparisons,
        static fn (array $row): bool => ($row['localParsed'] ?? false) === true && ($row['pandocParsed'] ?? false) === true
    ));
    $parseFailureCount = count(array_filter(
        $fixtureComparisons,
        static fn (array $row): bool => ($row['localParsed'] ?? false) !== true
            || ($row['pandocParsed'] ?? false) !== true
            || ($row['nativeFixtureParsed'] ?? false) !== true
    ));
    $localPandocMatchCount = count(array_filter($fixtureComparisons, static fn (array $row): bool => ($row['localPandocStatus'] ?? null) === 'matched'));
    $pandocNativeFixtureMatchCount = count(array_filter($fixtureComparisons, static fn (array $row): bool => ($row['pandocNativeFixtureStatus'] ?? null) === 'matched'));
    $localPandocMismatchCount = $comparedCount - $localPandocMatchCount;
    $pandocNativeFixtureMismatchCount = $comparedCount - $pandocNativeFixtureMatchCount;

    return [
        'status' => 'completed',
        'format' => $format,
        'fixtureDirectory' => $fixtureDirectory,
        'sourceExtension' => $extension,
        'totalAvailableCount' => $totalAvailableCount,
        'candidateScannedCount' => $comparedCount + count($skippedCandidateComparisons),
        'skippedCandidateCount' => count($skippedCandidateComparisons),
        'comparedCount' => $comparedCount,
        'localParsedCount' => $localParsedCount,
        'pandocParsedCount' => $pandocParsedCount,
        'nativeFixtureParsedCount' => $nativeFixtureParsedCount,
        'bothParsedCount' => $bothParsedCount,
        'parseFailureCount' => $parseFailureCount,
        'localPandocMatchCount' => $localPandocMatchCount,
        'localPandocMismatchCount' => $localPandocMismatchCount,
        'pandocNativeFixtureMatchCount' => $pandocNativeFixtureMatchCount,
        'pandocNativeFixtureMismatchCount' => $pandocNativeFixtureMismatchCount,
        'fixtureComparisons' => $fixtureComparisons,
        'skippedCandidateComparisons' => $skippedCandidateComparisons,
    ];
}

/**
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function summarizeInlineFormat(array $report): array
{
    $compared = (int) ($report['comparedCount'] ?? 0);
    $matched = (int) ($report['localPandocMatchCount'] ?? 0);

    return [
        'status' => (string) ($report['status'] ?? 'unknown'),
        'totalAvailableCount' => (int) ($report['totalAvailableCount'] ?? 0),
        'candidateScannedCount' => (int) ($report['candidateScannedCount'] ?? 0),
        'skippedCandidateCount' => (int) ($report['skippedCandidateCount'] ?? 0),
        'comparedCount' => $compared,
        'localParsedCount' => (int) ($report['localParsedCount'] ?? 0),
        'pandocParsedCount' => (int) ($report['pandocParsedCount'] ?? 0),
        'nativeFixtureParsedCount' => (int) ($report['nativeFixtureParsedCount'] ?? 0),
        'parseFailureCount' => (int) ($report['parseFailureCount'] ?? 0),
        'localPandocMatchCount' => $matched,
        'localPandocMismatchCount' => (int) ($report['localPandocMismatchCount'] ?? 0),
        'pandocNativeFixtureMatchCount' => (int) ($report['pandocNativeFixtureMatchCount'] ?? 0),
        'pandocNativeFixtureMismatchCount' => (int) ($report['pandocNativeFixtureMismatchCount'] ?? 0),
        'matchPercent' => percent($matched, $compared),
        'result' => $compared > 0
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['localPandocMismatchCount'] ?? -1) === 0
            && (int) ($report['pandocNativeFixtureMismatchCount'] ?? -1) === 0
                ? 'passed'
                : 'failed',
        'fixtureComparisons' => $report['fixtureComparisons'] ?? [],
        'skippedCandidateComparisons' => $report['skippedCandidateComparisons'] ?? [],
    ];
}

/**
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function summarizeExecutableHarnessFormat(array $report, string $kind): array
{
    $comparedKey = $kind === 'epub' ? 'comparedEpubCount' : 'comparedPptxCount';
    $totalKey = $kind === 'epub' ? 'totalEpubCount' : 'totalPptxCount';
    $matched = (int) ($report['normalizedAstMatchCount'] ?? 0);
    $compared = (int) ($report[$comparedKey] ?? 0);

    return [
        'status' => (string) ($report['status'] ?? 'unknown'),
        'totalAvailableCount' => (int) ($report[$totalKey] ?? 0),
        'candidateScannedCount' => $compared,
        'skippedCandidateCount' => 0,
        'comparedCount' => $compared,
        'localParsedCount' => (int) ($report['localParsedCount'] ?? 0),
        'pandocParsedCount' => (int) ($report['pandocParsedCount'] ?? 0),
        'nativeFixtureParsedCount' => (int) ($report['nativeFixtureParsedCount'] ?? 0),
        'parseFailureCount' => (int) ($report['parseFailureCount'] ?? 0),
        'localPandocMatchCount' => $matched,
        'localPandocMismatchCount' => (int) ($report['normalizedAstMismatchCount'] ?? 0),
        'pandocNativeFixtureMatchCount' => (int) ($report['pandocNativeFixtureMatchCount'] ?? 0),
        'pandocNativeFixtureMismatchCount' => (int) ($report['pandocNativeFixtureMismatchCount'] ?? 0),
        'matchPercent' => percent($matched, $compared),
        'astParityStatus' => $report['astParityStatus'] ?? null,
        'result' => $compared > 0
            && (int) ($report['parseFailureCount'] ?? -1) === 0
            && (int) ($report['normalizedAstMismatchCount'] ?? -1) === 0
            && (int) ($report['pandocNativeFixtureMismatchCount'] ?? -1) === 0
                ? 'passed'
                : 'failed',
        'fixtureComparisons' => $report['fixtureComparisons'] ?? [],
    ];
}

/**
 * @param array<string, mixed> $report
 * @return array<string, mixed>
 */
function compactExecutableHarnessReport(array $report): array
{
    return array_intersect_key($report, array_flip([
        'schemaVersion',
        'tool',
        'status',
        'skipped',
        'reason',
        'verdict',
        'evidenceKind',
        'pandocExecutable',
        'pandocVersion',
        'totalEpubCount',
        'comparedEpubCount',
        'totalPptxCount',
        'comparedPptxCount',
        'localParsedCount',
        'pandocParsedCount',
        'nativeFixtureParsedCount',
        'bothParsedCount',
        'parseFailureCount',
        'normalizedAstMatchCount',
        'normalizedAstMismatchCount',
        'pandocNativeFixtureMatchCount',
        'pandocNativeFixtureMismatchCount',
        'pandocNativeFixtureByteMatchCount',
        'pandocNativeFixtureByteMismatchCount',
        'astParityStatus',
        'fixtureComparisons',
    ]));
}

/**
 * @param array<string, mixed> $options
 */
function htmlPandocFormat(array $options): string
{
    if (!array_key_exists('htmlRawHtml', $options)) {
        return 'html';
    }

    return (bool) $options['htmlRawHtml'] ? 'html+raw_html' : 'html-raw_html';
}

/**
 * @param array<string, mixed> $options
 */
function markdownPandocFormat(array $options): string
{
    $format = $options['format'] ?? 'markdown';

    return is_string($format) && $format !== '' ? $format : 'markdown';
}

/**
 * @param array<string, mixed> $options
 * @return array{ok: bool, document: ?AstNode, error: ?string}
 */
function readLocalInlineFixture(string $format, string $path, array $options): array
{
    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException("Unable to read fixture '{$path}'.");
        }

        $document = $format === 'html'
            ? (new HtmlReader($options))->read($bytes)
            : (new MarkdownReader($options))->read($bytes);

        return ['ok' => true, 'document' => $document, 'error' => null];
    } catch (Throwable $exception) {
        return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
    }
}

/**
 * @param array<string, mixed> $options
 * @return array{ok: bool, document: ?AstNode, error: ?string}
 */
function readLocalManifestFixture(string $format, string $path, array $options): array
{
    try {
        $bytes = file_get_contents($path);
        if (!is_string($bytes)) {
            throw new RuntimeException("Unable to read fixture '{$path}'.");
        }

        $document = match ($format) {
            'html' => (new HtmlReader($options))->read($bytes),
            'markdown' => (new MarkdownReader($options))->read($bytes),
            'epub' => (new EpubReader($options))->read($bytes),
            'pptx' => (new PptxReader($options))->read($bytes),
            default => throw new RuntimeException("Unsupported format '{$format}'."),
        };

        return ['ok' => true, 'document' => $document, 'error' => null];
    } catch (Throwable $exception) {
        return ['ok' => false, 'document' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
    }
}

function normalizerForFormat(string $format): object
{
    return match ($format) {
        'html' => new HtmlNativeAstComparisonHarness(),
        'markdown' => new MarkdownNativeAstComparisonHarness(),
        'epub' => new EpubNativeAstPackageComparisonHarness(),
        'pptx' => new PptxNativeAstComparisonHarness(),
        default => throw new RuntimeException("Unsupported format '{$format}'."),
    };
}

/**
 * @return array{ok: bool, document: ?AstNode, native: ?string, error: ?string}
 */
function readPandocNative(string $pandoc, string $format, string $path): array
{
    $result = runCommand([
        $pandoc,
        '-f',
        $format,
        '-t',
        'native',
        $path,
    ]);
    if ($result['exitCode'] !== 0) {
        return [
            'ok' => false,
            'document' => null,
            'native' => null,
            'error' => trim($result['stderr']) !== ''
                ? trim($result['stderr'])
                : 'pandoc exited with code ' . (string) $result['exitCode'],
        ];
    }

    try {
        $native = $result['stdout'];

        return [
            'ok' => true,
            'document' => (new NativeReader())->read($native),
            'native' => $native,
            'error' => null,
        ];
    } catch (Throwable $exception) {
        return ['ok' => false, 'document' => null, 'native' => $result['stdout'], 'error' => $exception::class . ': ' . $exception->getMessage()];
    }
}

/**
 * @return array{ok: bool, document: ?AstNode, native: ?string, error: ?string}
 */
function readNativeFixture(NativeReader $reader, string $path): array
{
    try {
        $native = file_get_contents($path);
        if (!is_string($native)) {
            throw new RuntimeException("Unable to read native fixture '{$path}'.");
        }

        return ['ok' => true, 'document' => $reader->read($native), 'native' => $native, 'error' => null];
    } catch (Throwable $exception) {
        return ['ok' => false, 'document' => null, 'native' => null, 'error' => $exception::class . ': ' . $exception->getMessage()];
    }
}

/**
 * @return array<string, string>
 */
function filesByBasename(string $directory, string $extension): array
{
    $files = [];
    foreach (glob(rtrim($directory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '*.' . $extension) ?: [] as $path) {
        if (!is_file($path)) {
            continue;
        }
        $files[pathinfo($path, PATHINFO_FILENAME)] = $path;
    }
    ksort($files, SORT_STRING);

    return $files;
}

/**
 * @param list<string> $command
 * @return array{stdout: string, stderr: string, exitCode: int}
 */
function runCommand(array $command): array
{
    $commandLine = implode(' ', array_map('escapeshellarg', $command));
    $pipes = [];
    $process = proc_open(
        $commandLine,
        [
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes
    );
    if (!is_resource($process)) {
        return ['stdout' => '', 'stderr' => 'proc_open failed', 'exitCode' => 127];
    }

    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $exitCode = proc_close($process);

    return [
        'stdout' => is_string($stdout) ? $stdout : '',
        'stderr' => is_string($stderr) ? $stderr : '',
        'exitCode' => $exitCode,
    ];
}

function resolveExecutable(string $candidate): ?string
{
    if (str_contains($candidate, DIRECTORY_SEPARATOR)) {
        return is_file($candidate) && is_executable($candidate) ? $candidate : null;
    }

    $output = [];
    $exitCode = 0;
    exec('command -v ' . escapeshellarg($candidate), $output, $exitCode);
    if ($exitCode !== 0 || ($output[0] ?? '') === '') {
        return null;
    }

    return (string) $output[0];
}

function pandocVersion(string $pandoc): string
{
    $result = runCommand([$pandoc, '--version']);
    if ($result['exitCode'] !== 0) {
        return 'unknown';
    }

    $lines = preg_split('/\R/', trim($result['stdout']));

    return is_array($lines) && isset($lines[0]) ? $lines[0] : 'unknown';
}

function firstDifference(mixed $left, mixed $right, string $path = 'root'): ?string
{
    if (get_debug_type($left) !== get_debug_type($right)) {
        return "{$path}: type " . get_debug_type($left) . ' !== ' . get_debug_type($right);
    }
    if (is_array($left)) {
        if (array_keys($left) !== array_keys($right)) {
            $leftKeys = implode(',', array_map('strval', array_keys($left)));
            $rightKeys = implode(',', array_map('strval', array_keys($right)));

            return "{$path}: keys [{$leftKeys}] !== [{$rightKeys}]";
        }
        foreach ($left as $key => $leftChild) {
            $difference = firstDifference($leftChild, $right[$key], $path . '[' . (string) $key . ']');
            if ($difference !== null) {
                return $difference;
            }
        }

        return null;
    }
    if ($left !== $right) {
        return "{$path}: " . previewScalar($left) . ' !== ' . previewScalar($right);
    }

    return null;
}

function previewScalar(mixed $value): string
{
    if (is_string($value)) {
        $value = strlen($value) > 120 ? substr($value, 0, 117) . '...' : $value;

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
    if (is_bool($value)) {
        return $value ? 'true' : 'false';
    }
    if ($value === null) {
        return 'null';
    }

    return (string) $value;
}

function percent(int $part, int $whole): ?float
{
    if ($whole === 0) {
        return null;
    }

    return round(($part / $whole) * 100, 2);
}

/**
 * @param array<string, mixed> $formats
 */
function formatsHaveRequiredMatches(array $formats, ?int $limitPerFormat): bool
{
    foreach ($formats as $summary) {
        if (!is_array($summary)) {
            return false;
        }
        if ($limitPerFormat !== null && (int) ($summary['comparedCount'] ?? -1) !== $limitPerFormat) {
            return false;
        }
        if ($limitPerFormat === null && (int) ($summary['comparedCount'] ?? 0) <= 0) {
            return false;
        }
        if (($summary['result'] ?? null) !== 'passed') {
            return false;
        }
    }

    return true;
}

/**
 * @param array<string, mixed> $report
 */
function reportHasRequiredMatches(array $report, ?int $limitPerFormat): bool
{
    $formats = $report['formats'] ?? null;

    return is_array($formats) && formatsHaveRequiredMatches($formats, $limitPerFormat);
}

/**
 * @param array<string, mixed> $report
 */
function summaryLine(array $report): string
{
    $parts = [];
    foreach (($report['formats'] ?? []) as $name => $summary) {
        if (!is_array($summary)) {
            continue;
        }
        $parts[] = sprintf(
            '%s=%d/%d %s',
            (string) $name,
            (int) ($summary['localPandocMatchCount'] ?? 0),
            (int) ($summary['comparedCount'] ?? 0),
            (string) ($summary['result'] ?? 'unknown')
        );
    }

    return 'Summary: ' . implode(', ', $parts);
}

/**
 * @param array<string, mixed> $report
 */
function renderHtmlReport(array $report): string
{
    $formats = is_array($report['formats'] ?? null) ? $report['formats'] : [];
    $rawReports = is_array($report['rawReports'] ?? null) ? $report['rawReports'] : [];
    $status = (string) ($report['status'] ?? 'unknown');
    $summaryRows = '';
    foreach ($formats as $name => $summary) {
        if (!is_array($summary)) {
            continue;
        }
        $summaryRows .= '<tr>'
            . '<th>' . h((string) $name) . '</th>'
            . '<td>' . h((string) ($summary['result'] ?? 'unknown')) . '</td>'
            . '<td>' . h((string) ($summary['localPandocMatchCount'] ?? 0)) . ' / ' . h((string) ($summary['comparedCount'] ?? 0)) . '</td>'
            . '<td>' . summaryNativeCell($summary) . '</td>'
            . '<td>' . h((string) ($summary['parseFailureCount'] ?? 0)) . '</td>'
            . '<td>' . h((string) ($summary['skippedCandidateCount'] ?? 0)) . '</td>'
            . '<td>' . h((string) ($summary['totalAvailableCount'] ?? 0)) . '</td>'
            . '</tr>';
    }

    $sections = '';
    foreach ($formats as $name => $summary) {
        if (!is_array($summary)) {
            continue;
        }
        $rows = '';
        $fixtureRows = is_array($summary['fixtureComparisons'] ?? null) ? $summary['fixtureComparisons'] : [];
        foreach ($fixtureRows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $detail = '';
            foreach (['localError', 'pandocError', 'nativeFixtureError', 'localPandocFirstDifference', 'pandocNativeFixtureFirstDifference'] as $key) {
                if (isset($row[$key]) && is_scalar($row[$key])) {
                    $detail .= '<div><strong>' . h($key) . ':</strong> ' . h((string) $row[$key]) . '</div>';
                }
            }
            if ($detail === '') {
                $detail = '<span class="muted">matched</span>';
            }
            $features = is_array($row['features'] ?? null) ? implode(', ', array_map('strval', $row['features'])) : '';
            $sourceUrl = isset($row['sourceUrl']) && is_string($row['sourceUrl']) && $row['sourceUrl'] !== ''
                ? '<a href="' . h($row['sourceUrl']) . '">' . h((string) ($row['sourceKind'] ?? 'source')) . '</a>'
                : h((string) ($row['sourceKind'] ?? ''));
            $rows .= '<tr>'
                . '<td>' . h((string) ($row['fixture'] ?? 'unknown')) . '</td>'
                . '<td>' . $sourceUrl . '</td>'
                . '<td>' . h($features) . '</td>'
                . '<td><code>' . h((string) ($row['pandocFormat'] ?? (($name === 'epub' || $name === 'pptx') ? $name : 'unknown'))) . '</code></td>'
                . '<td>' . statusBadge((string) ($row['status'] ?? 'unknown')) . '</td>'
                . '<td>' . h((string) ($row['localPandocStatus'] ?? 'unknown')) . '</td>'
                . '<td>' . h((string) ($row['pandocNativeFixtureStatus'] ?? 'unknown')) . '</td>'
                . '<td>' . $detail . '</td>'
                . '</tr>';
        }
        $raw = is_array($rawReports[$name] ?? null) ? $rawReports[$name] : [];
        $sections .= '<section>'
            . '<h2>' . h(strtoupper((string) $name)) . '</h2>'
            . '<p class="metric">'
            . h((string) ($summary['localPandocMatchCount'] ?? 0)) . ' of '
            . h((string) ($summary['comparedCount'] ?? 0))
            . ' local reader outputs matched Haskell pandoc normalized native AST.'
            . '</p>'
            . '<table><thead><tr><th>Fixture</th><th>Source</th><th>Features</th><th>Pandoc format</th><th>Overall</th><th>Local vs Haskell</th><th>Haskell vs checked-in native</th><th>Details</th></tr></thead><tbody>'
            . $rows
            . '</tbody></table>'
            . skippedCandidateDetails($summary)
            . '<details><summary>Compact raw report</summary><pre>' . h(json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)) . '</pre></details>'
            . '</section>';
    }

    $title = (string) ($report['title'] ?? 'Haskell Pandoc Reader Sample Comparison');
    $description = (string) ($report['description'] ?? '');

    return '<!doctype html>'
        . '<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<title>' . h($title) . '</title>'
        . '<style>'
        . 'body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;margin:0;background:#f7f7f4;color:#20211f;}'
        . 'main{max-width:1180px;margin:0 auto;padding:32px 20px 56px;}'
        . 'h1{font-size:28px;margin:0 0 8px;}h2{font-size:20px;margin:32px 0 10px;}'
        . '.meta{color:#5d6259;margin:0 0 22px;}.status{display:inline-block;padding:3px 9px;border-radius:999px;font-size:13px;font-weight:700;}'
        . '.passed{background:#d9f2df;color:#115c2a;}.failed,.mismatched,.parse-failure{background:#ffe1dc;color:#8b1e12;}.matched{background:#d9f2df;color:#115c2a;}.unknown{background:#e8e8e1;color:#4f514d;}'
        . 'table{width:100%;border-collapse:collapse;background:#fff;border:1px solid #d7d9d1;margin:12px 0 20px;}'
        . 'th,td{padding:8px 10px;border-bottom:1px solid #e5e6df;text-align:left;vertical-align:top;font-size:13px;}th{background:#eeeee8;font-weight:700;}'
        . 'code{font-family:ui-monospace,SFMono-Regular,Menlo,monospace;font-size:12px;}pre{white-space:pre-wrap;overflow:auto;background:#20211f;color:#f6f6ef;padding:12px;border-radius:6px;font-size:12px;}'
        . 'section{margin-top:26px;}.metric{font-weight:700;}.muted{color:#74776f;}details{margin-bottom:22px;}'
        . '</style></head><body><main>'
        . '<h1>' . h($title) . '</h1>'
        . '<p class="meta">Generated ' . h((string) ($report['generatedAt'] ?? 'unknown'))
        . ' with ' . h((string) ($report['pandocVersion'] ?? 'unknown'))
        . ' at <code>' . h((string) ($report['pandocExecutable'] ?? '')) . '</code>. '
        . sampleSizeText($report) . '</p>'
        . ($description !== '' ? '<p>' . h($description) . '</p>' : '')
        . '<p>Overall status: ' . statusBadge($status) . '</p>'
        . '<table><thead><tr><th>Format</th><th>Result</th><th>Local vs Haskell</th><th>Haskell vs checked-in native</th><th>Parse failures</th><th>Skipped candidates</th><th>Available fixtures</th></tr></thead><tbody>'
        . $summaryRows
        . '</tbody></table>'
        . $sections
        . '</main></body></html>';
}

/**
 * @param array<string, mixed> $summary
 */
function skippedCandidateDetails(array $summary): string
{
    $rows = is_array($summary['skippedCandidateComparisons'] ?? null) ? $summary['skippedCandidateComparisons'] : [];
    if ($rows === []) {
        return '';
    }

    $body = '';
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $detail = '';
        foreach (['localPandocFirstDifference', 'pandocNativeFixtureFirstDifference', 'localError', 'pandocError', 'nativeFixtureError'] as $key) {
            if (isset($row[$key]) && is_scalar($row[$key])) {
                $detail .= '<div><strong>' . h($key) . ':</strong> ' . h((string) $row[$key]) . '</div>';
            }
        }
        $body .= '<tr>'
            . '<td>' . h((string) ($row['fixture'] ?? 'unknown')) . '</td>'
            . '<td><code>' . h((string) ($row['pandocFormat'] ?? 'unknown')) . '</code></td>'
            . '<td>' . h((string) ($row['status'] ?? 'unknown')) . '</td>'
            . '<td>' . ($detail !== '' ? $detail : '<span class="muted">no detail</span>') . '</td>'
            . '</tr>';
    }

    return '<details><summary>Skipped candidates before the selected sample</summary>'
        . '<table><thead><tr><th>Fixture</th><th>Pandoc format</th><th>Status</th><th>Reason</th></tr></thead><tbody>'
        . $body
        . '</tbody></table></details>';
}

function statusBadge(string $status): string
{
    $class = preg_replace('/[^a-z0-9_-]+/i', '-', strtolower($status));
    $class = is_string($class) && $class !== '' ? $class : 'unknown';

    return '<span class="status ' . h($class) . '">' . h($status) . '</span>';
}

/**
 * @param array<string, mixed> $summary
 */
function summaryNativeCell(array $summary): string
{
    if (($summary['nativeFixtureRequired'] ?? true) === false) {
        return '<span class="muted">not required</span>';
    }

    return h((string) ($summary['pandocNativeFixtureMatchCount'] ?? 0))
        . ' / '
        . h((string) ($summary['comparedCount'] ?? 0));
}

/**
 * @param array<string, mixed> $report
 */
function sampleSizeText(array $report): string
{
    if (($report['mode'] ?? null) === 'manifest') {
        return 'Manifest: <code>' . h((string) ($report['manifestPath'] ?? '')) . '</code>.';
    }

    return 'Sample size: ' . h((string) ($report['limitPerFormat'] ?? '')) . ' documents per format.';
}

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function normalizeOutputPath(string $repoRoot, string $path): string
{
    if ($path === '') {
        throw new InvalidArgumentException('Output path must not be empty');
    }

    return str_starts_with($path, DIRECTORY_SEPARATOR) ? $path : $repoRoot . DIRECTORY_SEPARATOR . $path;
}

function writeFileWithDirectory(string $path, string $contents): void
{
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create directory '{$directory}'.");
    }
    if (file_put_contents($path, $contents) === false) {
        throw new RuntimeException("Unable to write '{$path}'.");
    }
}

function relativePath(string $base, string $path): string
{
    $base = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

    return str_starts_with($path, $base) ? substr($path, strlen($base)) : $path;
}
