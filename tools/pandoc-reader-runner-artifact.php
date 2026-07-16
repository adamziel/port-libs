#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\DelimitedTextUpstreamReaderEvidence;
use PortLibs\Pandoc\EpubUpstreamReaderEvidence;
use PortLibs\Pandoc\HtmlUpstreamReaderEvidence;
use PortLibs\Pandoc\MarkdownUpstreamReaderEvidence;
use PortLibs\Pandoc\PptxUpstreamReaderEvidence;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-reader-runner-artifact.php --format=FORMAT [options]

Formats:
  pptx, markdown, html, epub, delimited-text

Options:
  --json                              Emit JSON instead of text.
  --repo-root PATH                    Repository root. Defaults to the parent of tools/.
  --upstream-root PATH                Hydrated Pandoc upstream checkout root.
  --fixture-base PATH                 EPUB-only fixture base for checked-in EPUB fixture snapshots.
  --artifact-root PATH                Directory containing/writing result.json.
  --log-root PATH                     Directory containing required runner transcripts.
  --write-result-artifact PATH        Write result.json from existing transcripts.
  --result-started-at-utc TIMESTAMP   UTC start timestamp for written result.json.
  --result-finished-at-utc TIMESTAMP  UTC finish timestamp for written result.json.
  --runner-result-artifact PATH       Validate an existing result.json instead of the default path.
  --require-valid-result-artifact     Exit 1 unless the result artifact validates.
  --help                              Show this help.

This tool does not execute Cabal/Tasty. It turns the existing per-reader
runner plan plus captured transcript files into the result.json shape that the
format-specific evidence gates already validate.
TEXT;
};

try {
    $repoRoot = dirname(__DIR__);
    $format = null;
    $upstreamRoot = null;
    $fixtureBase = null;
    $artifactRoot = null;
    $logRoot = '.port-libs/pandoc-runner/logs';
    $writeResultArtifact = null;
    $runnerResultArtifact = null;
    $resultStartedAtUtc = null;
    $resultFinishedAtUtc = null;
    $json = false;
    $requireValidResultArtifact = false;
    $args = array_slice($argv, 1);

    for ($i = 0, $count = count($args); $i < $count; ++$i) {
        $arg = $args[$i];
        $nextValue = static function (string $name) use ($args, &$i, $count): string {
            if ($i + 1 >= $count) {
                throw new InvalidArgumentException("Missing value for {$name}");
            }
            ++$i;

            return $args[$i];
        };

        if ($arg === '--help' || $arg === '-h') {
            fwrite(STDOUT, $usage() . PHP_EOL);
            exit(0);
        }
        if ($arg === '--json') {
            $json = true;
            continue;
        }
        if ($arg === '--require-valid-result-artifact') {
            $requireValidResultArtifact = true;
            continue;
        }
        foreach ([
            '--format' => 'format',
            '--repo-root' => 'repoRoot',
            '--upstream-root' => 'upstreamRoot',
            '--fixture-base' => 'fixtureBase',
            '--artifact-root' => 'artifactRoot',
            '--log-root' => 'logRoot',
            '--write-result-artifact' => 'writeResultArtifact',
            '--runner-result-artifact' => 'runnerResultArtifact',
            '--result-started-at-utc' => 'resultStartedAtUtc',
            '--result-finished-at-utc' => 'resultFinishedAtUtc',
        ] as $option => $variable) {
            if ($arg === $option) {
                ${$variable} = $nextValue($option);
                continue 2;
            }
            if (str_starts_with($arg, $option . '=')) {
                ${$variable} = substr($arg, strlen($option) + 1);
                continue 2;
            }
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    if (!is_string($format) || $format === '') {
        throw new InvalidArgumentException('--format is required');
    }

    $repoRoot = rtrim($repoRoot, DIRECTORY_SEPARATOR);
    $format = normalizeFormat($format);
    $upstreamRoot ??= defaultUpstreamRoot($format);
    $artifactRoot ??= defaultArtifactRoot($format);
    $defaultResultArtifact = trim($artifactRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'result.json';
    $resultArtifactForValidation = $runnerResultArtifact ?? $writeResultArtifact ?? $defaultResultArtifact;

    $baseReport = evidenceReport($format, $repoRoot, $upstreamRoot, $fixtureBase, null);
    $runnerPlan = runnerEvidence($baseReport);
    $testNames = expectedTestNames($format, $baseReport);
    $transcriptPaths = stringList($runnerPlan['requiredTranscripts'] ?? []);
    $resultArtifact = null;

    if ($writeResultArtifact !== null) {
        if (!is_string($resultStartedAtUtc) || $resultStartedAtUtc === '') {
            throw new InvalidArgumentException('--result-started-at-utc is required with --write-result-artifact');
        }
        if (!is_string($resultFinishedAtUtc) || $resultFinishedAtUtc === '') {
            throw new InvalidArgumentException('--result-finished-at-utc is required with --write-result-artifact');
        }

        $transcriptEvidence = transcriptEvidence($repoRoot, $logRoot, $runnerPlan, $testNames);
        if (($transcriptEvidence['status'] ?? null) !== 'valid-targeted-runner-transcripts') {
            $issues = implode(', ', stringList($transcriptEvidence['issues'] ?? []));
            throw new RuntimeException('Runner transcripts do not prove the targeted tests: ' . $issues);
        }

        $resultArtifact = buildResultArtifact(
            $runnerPlan,
            $testNames,
            transcriptRecords($repoRoot, $logRoot, $transcriptPaths),
            $transcriptEvidence,
            $resultStartedAtUtc,
            $resultFinishedAtUtc
        );
        writeJsonFile($repoRoot, $writeResultArtifact, $resultArtifact);
        $resultArtifactForValidation = $writeResultArtifact;
    }

    $validatedReport = evidenceReport($format, $repoRoot, $upstreamRoot, $fixtureBase, $resultArtifactForValidation);
    $validatedRunner = runnerEvidence($validatedReport);
    $validation = is_array($validatedRunner['validation'] ?? null) ? $validatedRunner['validation'] : [];
    $valid = str_starts_with((string) ($validation['status'] ?? ''), 'valid-upstream-')
        && ($validation['issues'] ?? null) === [];

    $report = [
        'schemaVersion' => 1,
        'tool' => 'pandoc-reader-runner-artifact',
        'format' => $format,
        'status' => $valid ? 'runner-result-artifact-valid' : 'runner-result-artifact-not-valid',
        'repoRoot' => displayPath($repoRoot, $repoRoot),
        'upstreamRoot' => $upstreamRoot,
        'artifactRoot' => $artifactRoot,
        'logRoot' => $logRoot,
        'runner' => [
            'name' => $runnerPlan['runner'] ?? null,
            'target' => $runnerPlan['target'] ?? null,
            'futureCommands' => $runnerPlan['futureCommands'] ?? [],
            'futureCommandLines' => commandLines($runnerPlan['futureCommands'] ?? []),
            'requiredTranscripts' => $transcriptPaths,
            'requiredArtifacts' => $runnerPlan['requiredArtifacts'] ?? [],
        ],
        'expectedTestNames' => $testNames,
        'resultArtifact' => [
            'written' => $writeResultArtifact !== null,
            'path' => displayPath(absolutePath($resultArtifactForValidation, $repoRoot), $repoRoot),
            'payload' => $resultArtifact,
        ],
        'validation' => $validation,
        'claim' => 'Assembles and validates reader-runner result artifacts from existing transcripts only; it does not execute Cabal/Tasty.',
    ];

    if ($json) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, textReport($report));
    }

    if ($requireValidResultArtifact && !$valid) {
        exit(1);
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-reader-runner-artifact: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-reader-runner-artifact: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}

function normalizeFormat(string $format): string
{
    $normalized = strtolower(str_replace('_', '-', $format));
    if ($normalized === 'csv' || $normalized === 'tsv' || $normalized === 'delimited') {
        return 'delimited-text';
    }
    if (!in_array($normalized, ['pptx', 'markdown', 'html', 'epub', 'delimited-text'], true)) {
        throw new InvalidArgumentException("Unsupported format: {$format}");
    }

    return $normalized;
}

function defaultUpstreamRoot(string $format): string
{
    return match ($format) {
        'pptx' => PptxUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        'markdown' => MarkdownUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        'html' => HtmlUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        'epub' => EpubUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT,
        'delimited-text' => DelimitedTextUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT,
    };
}

function defaultArtifactRoot(string $format): string
{
    $stem = match ($format) {
        'delimited-text' => 'delimited-text',
        default => $format,
    };

    return '.port-libs/pandoc-runner/artifacts/' . $stem . '-targeted-run';
}

function evidenceReport(string $format, string $repoRoot, string $upstreamRoot, ?string $fixtureBase, ?string $runnerResultArtifact): array
{
    return match ($format) {
        'pptx' => (new PptxUpstreamReaderEvidence($repoRoot, $upstreamRoot, $runnerResultArtifact))->report(),
        'markdown' => (new MarkdownUpstreamReaderEvidence($repoRoot, $upstreamRoot, $runnerResultArtifact))->report(),
        'html' => (new HtmlUpstreamReaderEvidence($repoRoot, $upstreamRoot, $runnerResultArtifact))->report(),
        'epub' => (new EpubUpstreamReaderEvidence($repoRoot, $upstreamRoot, $fixtureBase, $runnerResultArtifact))->report(),
        'delimited-text' => (new DelimitedTextUpstreamReaderEvidence($repoRoot, $upstreamRoot, $runnerResultArtifact))->report(),
    };
}

function runnerEvidence(array $report): array
{
    $runner = $report['runnerEvidence'] ?? null;
    if (!is_array($runner)) {
        throw new RuntimeException('Evidence report does not expose runnerEvidence');
    }

    return $runner;
}

function expectedTestNames(string $format, array $report): array
{
    $denominator = is_array($report['denominator'] ?? null) ? $report['denominator'] : [];
    if ($format === 'delimited-text') {
        return ['Command: csv.md #1'];
    }
    if (is_array($denominator['readerCases'] ?? null)) {
        return array_values(array_filter(array_map(
            static fn (mixed $case): ?string => is_array($case) && is_string($case['name'] ?? null) ? $case['name'] : null,
            $denominator['readerCases']
        )));
    }
    if (is_array($denominator['selectedFixtures'] ?? null)) {
        return array_values(array_filter(array_map(
            static fn (mixed $fixture): ?string => is_array($fixture) && is_string($fixture['name'] ?? null) ? $fixture['name'] : null,
            $denominator['selectedFixtures']
        )));
    }

    return [];
}

function transcriptRecords(string $repoRoot, string $logRoot, array $paths): array
{
    $records = [];
    $absoluteLogRoot = absolutePath($logRoot, $repoRoot);
    foreach ($paths as $path) {
        if (!is_string($path) || $path === '') {
            continue;
        }
        $absolutePath = absoluteTranscriptPath($repoRoot, $absoluteLogRoot, $path);
        if (!is_file($absolutePath)) {
            throw new RuntimeException("Missing required runner transcript: {$path}");
        }
        $sha256 = hash_file('sha256', $absolutePath);
        $bytes = filesize($absolutePath);
        $records[] = [
            'path' => $path,
            'sha256' => is_string($sha256) ? $sha256 : '',
            'bytes' => is_int($bytes) ? $bytes : 0,
        ];
    }

    return $records;
}

function absoluteTranscriptPath(string $repoRoot, string $absoluteLogRoot, string $requiredPath): string
{
    $absoluteRequiredPath = absolutePath($requiredPath, $repoRoot);
    if (is_file($absoluteRequiredPath)) {
        return $absoluteRequiredPath;
    }

    return rtrim($absoluteLogRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . basename($requiredPath);
}

function buildResultArtifact(array $runnerPlan, array $testNames, array $transcripts, array $transcriptEvidence, string $startedAtUtc, string $finishedAtUtc): array
{
    $binding = is_array($runnerPlan['upstreamBinding'] ?? null) ? $runnerPlan['upstreamBinding'] : [];
    $futureCommands = is_array($runnerPlan['futureCommands'] ?? null) ? $runnerPlan['futureCommands'] : [];
    $command = $futureCommands[2] ?? null;
    if (!is_array($command)) {
        throw new RuntimeException('Runner plan does not include a run command');
    }

    return [
        'schemaVersion' => 2,
        'runner' => (string) ($runnerPlan['runner'] ?? ''),
        'runnerExecuted' => true,
        'upstream' => [
            'name' => (string) ($binding['name'] ?? 'jgm/pandoc'),
            'commit' => (string) ($binding['expectedCommit'] ?? ''),
        ],
        'target' => $runnerPlan['target'] ?? [],
        'command' => $command,
        'exitCode' => 0,
        'testCount' => count($testNames),
        'passedCount' => count($testNames),
        'failedCount' => 0,
        'skippedCount' => 0,
        'testNames' => array_values($testNames),
        'transcriptPaths' => stringList($runnerPlan['requiredTranscripts'] ?? []),
        'transcripts' => $transcripts,
        'transcriptEvidence' => $transcriptEvidence,
        'startedAtUtc' => $startedAtUtc,
        'finishedAtUtc' => $finishedAtUtc,
    ];
}

function transcriptEvidence(string $repoRoot, string $logRoot, array $runnerPlan, array $testNames): array
{
    $paths = stringList($runnerPlan['requiredTranscripts'] ?? []);
    $absoluteLogRoot = absolutePath($logRoot, $repoRoot);
    $dependencyPath = requiredTranscriptBySuffix($paths, 'runner-test-dependencies.txt');
    $listPath = requiredTranscriptBySuffix($paths, '-targeted-list-tests.txt');
    $runPath = requiredTranscriptBySuffix($paths, '-targeted-run.txt');
    $issues = [];

    $dependency = $dependencyPath === null ? null : transcriptText($repoRoot, $absoluteLogRoot, $dependencyPath);
    $list = $listPath === null ? null : transcriptText($repoRoot, $absoluteLogRoot, $listPath);
    $run = $runPath === null ? null : transcriptText($repoRoot, $absoluteLogRoot, $runPath);

    if ($dependencyPath === null || $dependency === null) {
        $issues[] = 'missing-dependency-transcript';
    }
    if ($listPath === null || $list === null) {
        $issues[] = 'missing-list-tests-transcript';
    }
    if ($runPath === null || $run === null) {
        $issues[] = 'missing-targeted-run-transcript';
    }
    if ($testNames === []) {
        $issues[] = 'missing-expected-test-names';
    }

    $missingFromList = $list === null ? $testNames : missingTranscriptTestNames($list, $testNames);
    $missingFromRun = $run === null ? $testNames : missingTranscriptTestNames($run, $testNames);
    if ($missingFromList !== []) {
        $issues[] = 'expected-test-names-missing-from-list-transcript';
    }
    if ($missingFromRun !== []) {
        $issues[] = 'expected-test-names-missing-from-run-transcript';
    }

    $dependencyExitCodeZero = $dependency !== null && str_contains($dependency, 'exitCode: 0');
    $listExitCodeZero = $list !== null && str_contains($list, 'exitCode: 0');
    $runExitCodeZero = $run !== null && str_contains($run, 'exitCode: 0');
    if (!$dependencyExitCodeZero) {
        $issues[] = 'dependency-transcript-missing-zero-exit-marker';
    }
    if (!$listExitCodeZero) {
        $issues[] = 'list-transcript-missing-zero-exit-marker';
    }
    if (!$runExitCodeZero) {
        $issues[] = 'run-transcript-missing-zero-exit-marker';
    }

    return [
        'status' => $issues === [] ? 'valid-targeted-runner-transcripts' : 'invalid-targeted-runner-transcripts',
        'issues' => array_values(array_unique($issues)),
        'expectedTestCount' => count($testNames),
        'listTranscriptPath' => $listPath,
        'runTranscriptPath' => $runPath,
        'dependencyTranscriptPath' => $dependencyPath,
        'expectedNamesObservedInListCount' => count($testNames) - count($missingFromList),
        'expectedNamesObservedInRunCount' => count($testNames) - count($missingFromRun),
        'missingFromList' => $missingFromList,
        'missingFromRun' => $missingFromRun,
        'exitMarkers' => [
            'dependency' => $dependencyExitCodeZero,
            'list' => $listExitCodeZero,
            'run' => $runExitCodeZero,
        ],
    ];
}

function requiredTranscriptBySuffix(array $paths, string $suffix): ?string
{
    foreach ($paths as $path) {
        if (str_ends_with($path, $suffix)) {
            return $path;
        }
    }

    return null;
}

function transcriptText(string $repoRoot, string $absoluteLogRoot, string $path): ?string
{
    $absolutePath = absoluteTranscriptPath($repoRoot, $absoluteLogRoot, $path);
    if (!is_file($absolutePath)) {
        return null;
    }

    return (string) file_get_contents($absolutePath);
}

function missingTranscriptTestNames(string $transcript, array $testNames): array
{
    $missing = [];
    foreach ($testNames as $testName) {
        if (!is_string($testName) || $testName === '') {
            continue;
        }
        if (!str_contains($transcript, $testName)) {
            $missing[] = $testName;
        }
    }

    return $missing;
}

function writeJsonFile(string $repoRoot, string $path, array $payload): void
{
    $absolutePath = absolutePath($path, $repoRoot);
    $directory = dirname($absolutePath);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create artifact directory: {$directory}");
    }
    $json = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL;
    if (file_put_contents($absolutePath, $json) === false) {
        throw new RuntimeException("Unable to write result artifact: {$absolutePath}");
    }
}

function commandLines(mixed $commands): array
{
    if (!is_array($commands)) {
        return [];
    }
    $lines = [];
    foreach ($commands as $command) {
        if (!is_array($command) || !is_string($command['program'] ?? null) || !is_array($command['arguments'] ?? null)) {
            continue;
        }
        $arguments = array_map(
            static fn (mixed $argument): string => escapeshellarg((string) $argument),
            $command['arguments']
        );
        $lines[] = trim($command['program'] . ' ' . implode(' ', $arguments));
    }

    return $lines;
}

function stringList(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }

    $strings = [];
    foreach ($value as $item) {
        if (is_string($item)) {
            $strings[] = $item;
        }
    }

    return $strings;
}

function absolutePath(string $path, string $repoRoot): string
{
    if ($path === '') {
        return $repoRoot;
    }
    if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return $path;
    }

    return rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
}

function displayPath(string $path, string $repoRoot): string
{
    $root = rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($path, $root)) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
    }

    return $path;
}

function textReport(array $report): string
{
    $validation = is_array($report['validation'] ?? null) ? $report['validation'] : [];
    $runner = is_array($report['runner'] ?? null) ? $report['runner'] : [];

    return implode(PHP_EOL, [
        'Pandoc reader runner artifact',
        'Format: ' . (string) ($report['format'] ?? ''),
        'Status: ' . (string) ($report['status'] ?? ''),
        'Runner: ' . (string) ($runner['name'] ?? ''),
        'Expected tests: ' . count(is_array($report['expectedTestNames'] ?? null) ? $report['expectedTestNames'] : []),
        'Result artifact: ' . (string) (($report['resultArtifact']['path'] ?? null) ?: 'not-evaluated'),
        'Validation: ' . (string) ($validation['status'] ?? 'unknown'),
        'Validation issues: ' . implode(', ', stringList($validation['issues'] ?? [])),
        '',
    ]);
}
