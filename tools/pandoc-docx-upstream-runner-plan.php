#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\DocxUpstreamRunnerPlan;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-docx-upstream-runner-plan.php [options]

Options:
  --json                Emit JSON instead of text.
  --repo-root PATH      Repository root. Defaults to the parent of tools/.
  --upstream-root PATH  Hydrated Pandoc upstream checkout root, relative to repo
                        root unless absolute. Defaults to .upstream-cache/pandoc-current.
  --write-selected-inventory PATH
                        Write the static selected DOCX test inventory JSON
                        artifact, relative to repo root unless absolute.
  --write-result-artifact PATH
                        Write result.json from existing selected inventory and
                        captured Cabal/Tasty transcripts. Does not execute
                        Cabal/Tasty.
  --result-started-at-utc TIMESTAMP
                        UTC start timestamp for --write-result-artifact,
                        formatted as YYYY-MM-DDTHH:MM:SSZ.
  --result-finished-at-utc TIMESTAMP
                        UTC finish timestamp for --write-result-artifact,
                        formatted as YYYY-MM-DDTHH:MM:SSZ.
  --validate-result-artifacts
                        Validate targeted-runner result artifacts without
                        executing Cabal/Tasty or recording a result.
  --artifact-root PATH  Directory containing selected-test-inventory.json and
                        result.json for --validate-result-artifacts. Defaults
                        to .port-libs/pandoc-runner/artifacts/docx-targeted-run.
  --log-root PATH       Directory containing targeted-runner transcripts for
                        --validate-result-artifacts. Defaults to
                        .port-libs/pandoc-runner/logs.
  --help                Show this help.

The plan is evidence-only. It checks the DOCX-specific upstream runner source
shape, can write a static selected test inventory artifact, and emits exact
future Cabal commands plus artifact paths. It does not run Cabal, execute
upstream tests, read DOCX package bytes, or claim DOCX parity.
TEXT;
};

$absolutePath = static function (string $path, string $repoRoot): string {
    if ($path === '') {
        return $path;
    }
    if (str_starts_with($path, DIRECTORY_SEPARATOR)) {
        return rtrim($path, DIRECTORY_SEPARATOR);
    }

    return rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
};

$displayPath = static function (string $path, string $repoRoot): string {
    $root = rtrim($repoRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (str_starts_with($path, $root)) {
        return str_replace(DIRECTORY_SEPARATOR, '/', substr($path, strlen($root)));
    }

    return $path;
};

try {
    $repoRoot = dirname(__DIR__);
    $upstreamRoot = DocxUpstreamRunnerPlan::DEFAULT_RELATIVE_UPSTREAM_ROOT;
    $selectedInventoryOutput = null;
    $resultArtifactOutput = null;
    $resultStartedAtUtc = null;
    $resultFinishedAtUtc = null;
    $validateResultArtifacts = false;
    $artifactRoot = DocxUpstreamRunnerPlan::DEFAULT_RELATIVE_ARTIFACT_ROOT;
    $logRoot = DocxUpstreamRunnerPlan::DEFAULT_RELATIVE_LOG_ROOT;
    $json = false;
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
        if ($arg === '--repo-root') {
            $repoRoot = $nextValue('--repo-root');
            continue;
        }
        if (str_starts_with($arg, '--repo-root=')) {
            $repoRoot = substr($arg, strlen('--repo-root='));
            continue;
        }
        if ($arg === '--upstream-root') {
            $upstreamRoot = $nextValue('--upstream-root');
            continue;
        }
        if (str_starts_with($arg, '--upstream-root=')) {
            $upstreamRoot = substr($arg, strlen('--upstream-root='));
            continue;
        }
        if ($arg === '--write-selected-inventory') {
            $selectedInventoryOutput = $nextValue('--write-selected-inventory');
            continue;
        }
        if (str_starts_with($arg, '--write-selected-inventory=')) {
            $selectedInventoryOutput = substr($arg, strlen('--write-selected-inventory='));
            continue;
        }
        if ($arg === '--write-result-artifact') {
            $resultArtifactOutput = $nextValue('--write-result-artifact');
            continue;
        }
        if (str_starts_with($arg, '--write-result-artifact=')) {
            $resultArtifactOutput = substr($arg, strlen('--write-result-artifact='));
            continue;
        }
        if ($arg === '--result-started-at-utc') {
            $resultStartedAtUtc = $nextValue('--result-started-at-utc');
            continue;
        }
        if (str_starts_with($arg, '--result-started-at-utc=')) {
            $resultStartedAtUtc = substr($arg, strlen('--result-started-at-utc='));
            continue;
        }
        if ($arg === '--result-finished-at-utc') {
            $resultFinishedAtUtc = $nextValue('--result-finished-at-utc');
            continue;
        }
        if (str_starts_with($arg, '--result-finished-at-utc=')) {
            $resultFinishedAtUtc = substr($arg, strlen('--result-finished-at-utc='));
            continue;
        }
        if ($arg === '--validate-result-artifacts') {
            $validateResultArtifacts = true;
            continue;
        }
        if ($arg === '--artifact-root') {
            $artifactRoot = $nextValue('--artifact-root');
            continue;
        }
        if (str_starts_with($arg, '--artifact-root=')) {
            $artifactRoot = substr($arg, strlen('--artifact-root='));
            continue;
        }
        if ($arg === '--log-root') {
            $logRoot = $nextValue('--log-root');
            continue;
        }
        if (str_starts_with($arg, '--log-root=')) {
            $logRoot = substr($arg, strlen('--log-root='));
            continue;
        }

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }

    $plan = new DocxUpstreamRunnerPlan($repoRoot, $upstreamRoot);
    $report = $plan->report();

    if ($selectedInventoryOutput !== null) {
        if ($selectedInventoryOutput === '') {
            throw new InvalidArgumentException('Selected inventory artifact path must not be empty');
        }

        $absoluteOutput = $absolutePath($selectedInventoryOutput, $repoRoot);
        $outputDirectory = dirname($absoluteOutput);
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
            throw new RuntimeException("Unable to create selected inventory artifact directory: {$outputDirectory}");
        }

        $payload = json_encode(
            $report['selectedTestInventory'],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        if (file_put_contents($absoluteOutput, $payload) === false) {
            throw new RuntimeException("Unable to write selected inventory artifact: {$absoluteOutput}");
        }

        $report['selectedTestInventoryArtifact'] = [
            'written' => true,
            'path' => $displayPath($absoluteOutput, $repoRoot),
            'bytes' => strlen($payload),
            'sha256' => hash('sha256', $payload),
            'evidenceKind' => DocxUpstreamRunnerPlan::SELECTED_TEST_INVENTORY_EVIDENCE_KIND,
            'claim' => 'Static selected DOCX source and fixture inventory artifact only; no Cabal command, upstream runner, or DOCX package comparison was executed.',
        ];
    }

    if ($resultArtifactOutput !== null) {
        if ($resultArtifactOutput === '') {
            throw new InvalidArgumentException('Result artifact path must not be empty');
        }
        if (!is_string($resultStartedAtUtc) || $resultStartedAtUtc === '') {
            throw new InvalidArgumentException('--result-started-at-utc is required with --write-result-artifact');
        }
        if (!is_string($resultFinishedAtUtc) || $resultFinishedAtUtc === '') {
            throw new InvalidArgumentException('--result-finished-at-utc is required with --write-result-artifact');
        }

        $resultArtifact = $plan->resultArtifactFromTranscripts(
            $artifactRoot,
            $logRoot,
            $resultStartedAtUtc,
            $resultFinishedAtUtc
        );
        $absoluteOutput = $absolutePath($resultArtifactOutput, $repoRoot);
        $outputDirectory = dirname($absoluteOutput);
        if (!is_dir($outputDirectory) && !mkdir($outputDirectory, 0777, true) && !is_dir($outputDirectory)) {
            throw new RuntimeException("Unable to create result artifact directory: {$outputDirectory}");
        }

        $payload = json_encode(
            $resultArtifact,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . PHP_EOL;
        if (file_put_contents($absoluteOutput, $payload) === false) {
            throw new RuntimeException("Unable to write result artifact: {$absoluteOutput}");
        }

        $report['resultArtifact'] = [
            'written' => true,
            'path' => $displayPath($absoluteOutput, $repoRoot),
            'bytes' => strlen($payload),
            'sha256' => hash('sha256', $payload),
            'evidenceKind' => DocxUpstreamRunnerPlan::RESULT_ARTIFACT_EVIDENCE_KIND,
            'runnerExecuted' => $resultArtifact['runnerExecuted'],
            'exitCode' => $resultArtifact['exitCode'],
            'selectedTestCount' => $resultArtifact['selectedTestCount'],
            'passedCount' => $resultArtifact['passedCount'],
            'failedCount' => $resultArtifact['failedCount'],
            'skippedCount' => $resultArtifact['skippedCount'],
            'claim' => 'Result artifact assembled from existing transcripts only; this tool did not execute Cabal/Tasty.',
        ];
    }

    if ($validateResultArtifacts) {
        $report['resultArtifactGate'] = $plan->resultArtifactGate($artifactRoot, $logRoot);
    }

    if ($json) {
        fwrite(STDOUT, json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR
        ) . PHP_EOL);
    } else {
        fwrite(STDOUT, DocxUpstreamRunnerPlan::formatTextReport($report));
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-docx-upstream-runner-plan: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-docx-upstream-runner-plan: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
