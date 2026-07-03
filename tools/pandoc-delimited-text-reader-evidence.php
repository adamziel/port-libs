#!/usr/bin/env php
<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\DelimitedTextUpstreamReaderEvidence;

$usage = static function (): string {
    return <<<'TEXT'
Usage: php tools/pandoc-delimited-text-reader-evidence.php [options]

Options:
  --json                          Emit JSON instead of text.
  --repo-root PATH                Repository root. Defaults to the parent of tools/.
  --require-honest-denominators   Exit 1 unless CSV/TSV direct fixture
                                  denominators are split honestly.
  --require-generated-tsv-native-parity
                                  Exit 1 unless the generated TSV-to-native
                                  samples match their native fixtures.
  --require-generated-csv-native-parity
                                  Exit 1 unless the generated CSV-to-native
                                  samples match their native fixtures.
  --require-runner-not-run        Exit 1 unless upstream runner evidence is
                                  structured as not-run for CSV and TSV.
  --require-no-validation-issues  Exit 1 when any validation issue is reported.
  --help                          Show this help.

This is a focused evidence gate for the native CSV/TSV reader packet.
It does not run Cabal/Tasty, execute pandoc, or claim upstream runner parity.
TEXT;
};

$validateHonestDenominators = static function (array $csv, array $tsv): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };

    $expect(($csv['reader'] ?? null) === 'csv', 'CSV evidence reader must be csv');
    $expect(str_contains((string) ($csv['source'] ?? ''), DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT), 'CSV evidence source must identify the pinned current upstream commit');
    $expect(($csv['denominatorScope'] ?? null) === 'direct-reader-fixtures', 'CSV denominator scope must be direct-reader-fixtures');
    $expect(($csv['denominator'] ?? null) === 2, 'CSV direct denominator must be 2');
    $expect(($csv['directFixtureDenominator'] ?? null) === 2, 'CSV direct fixture denominator must be 2');
    $expect(($csv['directFixtureCount'] ?? null) === 2, 'CSV direct fixture count must be 2');
    $expect(($csv['fixtures'] ?? null) === ($csv['directFixtures'] ?? null), 'CSV fixtures must be direct fixtures');
    $expect(($csv['csvDirectFixtureDenominator'] ?? null) === 2, 'CSV direct fixture split denominator must be 2');
    $expect(($csv['tsvDirectFixtureDenominator'] ?? null) === 0, 'TSV direct fixture split denominator must be 0');
    $expect(($csv['integrationFixtureCount'] ?? null) === 2, 'CSV-adjacent RST integration fixture count must stay separate at 2');
    $adjacent = is_array($csv['adjacentFixtureEvidence'] ?? null) ? $csv['adjacentFixtureEvidence'] : [];
    $adjacentFixtures = is_array($adjacent['fixtures'] ?? null) ? $adjacent['fixtures'] : [];
    $expect(($adjacent['relationship'] ?? null) === 'adjacent-rst-reader-fixtures-not-direct-delimited-text', 'CSV-adjacent RST relationship must identify non-direct delimited-text evidence');
    $expect(($adjacent['reader'] ?? null) === 'rst', 'CSV-adjacent fixture reader must be rst');
    $expect(($adjacent['directive'] ?? null) === 'csv-table', 'CSV-adjacent fixture directive must be csv-table');
    $expect(($adjacent['fixtureCount'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_ADJACENT_RST_FIXTURE_COUNT, 'CSV-adjacent RST fixture count must be 2');
    $expect(($adjacent['csvDirectFixtureDenominatorImpact'] ?? null) === 0, 'CSV-adjacent RST fixtures must not change the CSV direct denominator');
    $expect(($adjacent['tsvDirectFixtureDenominatorImpact'] ?? null) === 0, 'CSV-adjacent RST fixtures must not change the TSV direct denominator');
    $expect(array_column($adjacentFixtures, 'path') === [
        'test/command/3533-rst-csv-tables.csv',
        'test/command/3533-rst-csv-tables.md',
    ], 'CSV-adjacent RST fixture paths must be explicit');
    $expect(array_column($adjacentFixtures, 'directDelimitedTextReaderFixture') === [false, false], 'CSV-adjacent RST fixtures must be marked non-direct delimited text reader fixtures');

    $expect(($tsv['reader'] ?? null) === 'tsv', 'TSV evidence reader must be tsv');
    $expect(str_contains((string) ($tsv['source'] ?? ''), DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT), 'TSV evidence source must identify the pinned current upstream commit');
    $expect(($tsv['denominatorScope'] ?? null) === 'direct-reader-fixtures', 'TSV denominator scope must be direct-reader-fixtures');
    $expect(($tsv['denominator'] ?? null) === 0, 'TSV direct denominator must be 0');
    $expect(($tsv['directFixtureDenominator'] ?? null) === 0, 'TSV direct fixture denominator must be 0');
    $expect(($tsv['directFixtureCount'] ?? null) === 0, 'TSV direct fixture count must be 0');
    $expect(($tsv['fixtures'] ?? null) === [], 'TSV fixtures must not borrow CSV fixtures');
    $expect(($tsv['directFixtures'] ?? null) === [], 'TSV direct fixtures must be empty');
    $expect(($tsv['csvDirectFixtureDenominator'] ?? null) === 2, 'CSV direct fixture split denominator must remain visible from TSV evidence');
    $expect(($tsv['tsvDirectFixtureDenominator'] ?? null) === 0, 'TSV direct fixture split denominator must be 0');
    $expect(($tsv['integrationFixtureCount'] ?? null) === 0, 'TSV integration fixture count must be 0');
    $expect(($tsv['adjacentFixtureEvidence'] ?? null) === [], 'TSV adjacent fixture evidence must be empty');

    return $issues;
};

$validateGeneratedTsvNativeParity = static function (array $evidence): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };

    $expect(($evidence['reader'] ?? null) === 'tsv', 'Generated TSV native parity evidence reader must be tsv');
    $expect(($evidence['tsvDirectFixtureDenominator'] ?? null) === 0, 'Generated TSV native parity must keep TSV direct denominator at 0');
    $expect(($evidence['sampleCount'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, 'Generated TSV native parity sample count must match expected generated sample count');
    $expect(($evidence['comparedSampleCount'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, 'Generated TSV native parity compared sample count must match expected generated sample count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Generated TSV native parity parse failure count must be 0');
    $expect(($evidence['generatedNativeMatchCount'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT, 'Generated TSV native parity match count must match expected generated sample count');
    $expect(($evidence['generatedNativeMismatchCount'] ?? null) === 0, 'Generated TSV native parity mismatch count must be 0');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvNativeParity($evidence),
        'Generated TSV native parity helper must recognize required evidence'
    );

    return $issues;
};

$validateGeneratedCsvNativeParity = static function (array $evidence): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };

    $expect(($evidence['reader'] ?? null) === 'csv', 'Generated CSV native parity evidence reader must be csv');
    $expect(($evidence['csvDirectFixtureDenominator'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT, 'Generated CSV native parity must keep CSV direct denominator at 2');
    $expect(($evidence['sampleCount'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, 'Generated CSV native parity sample count must match expected generated sample count');
    $expect(($evidence['comparedSampleCount'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, 'Generated CSV native parity compared sample count must match expected generated sample count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Generated CSV native parity parse failure count must be 0');
    $expect(($evidence['generatedNativeMatchCount'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT, 'Generated CSV native parity match count must match expected generated sample count');
    $expect(($evidence['generatedNativeMismatchCount'] ?? null) === 0, 'Generated CSV native parity mismatch count must be 0');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvNativeParity($evidence),
        'Generated CSV native parity helper must recognize required evidence'
    );

    return $issues;
};

$validateRunnerNotRun = static function (array $csv, array $tsv): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };

    foreach (['CSV' => $csv, 'TSV' => $tsv] as $label => $evidence) {
        $runner = $evidence['runnerEvidence'] ?? [];
        $notRun = $evidence['notRunEvidence'][0] ?? [];

        $expect(($runner['status'] ?? null) === 'not-run', "{$label} runner status must be not-run");
        $expect(($runner['executed'] ?? null) === false, "{$label} runner executed flag must be false");
        $expect(array_key_exists('command', $runner) && $runner['command'] === null, "{$label} runner command must be null");
        $expect(array_key_exists('resultArtifact', $runner) && $runner['resultArtifact'] === null, "{$label} runner result artifact must be null");
        $expect(($notRun['scope'] ?? null) === 'upstream-haskell-runner', "{$label} not-run evidence scope must be upstream-haskell-runner");
        $expect(($notRun['status'] ?? null) === 'not-run', "{$label} not-run evidence status must be not-run");
        $expect(($notRun['executed'] ?? null) === false, "{$label} not-run evidence executed flag must be false");
    }

    return $issues;
};

$formatTextReport = static function (array $report): string {
    $csv = $report['csv'];
    $tsv = $report['tsv'];
    $generatedCsvNative = $report['generatedCsvNativeParity'];
    $generatedTsvNative = $report['generatedTsvNativeParity'];
    $lines = [
        'Delimited text reader evidence',
        'CSV direct denominator: ' . $csv['denominator'] . ' (' . $csv['denominatorScope'] . ')',
        'CSV direct fixtures: ' . implode(', ', $csv['fixtures']),
        'CSV adjacent RST integration fixtures: ' . $csv['integrationFixtureCount'],
        'CSV adjacent RST denominator impact: '
            . (int) (($csv['adjacentFixtureEvidence']['csvDirectFixtureDenominatorImpact'] ?? -1))
            . ' CSV, '
            . (int) (($csv['adjacentFixtureEvidence']['tsvDirectFixtureDenominatorImpact'] ?? -1))
            . ' TSV',
        'TSV direct denominator: ' . $tsv['denominator'] . ' (' . $tsv['denominatorScope'] . ')',
        'TSV direct fixtures: ' . (($tsv['fixtures'] ?? []) === [] ? 'none' : implode(', ', $tsv['fixtures'])),
        'Generated CSV native parity: ' . $generatedCsvNative['generatedNativeMatchCount']
            . '/' . $generatedCsvNative['sampleCount']
            . ' (' . $generatedCsvNative['parityStatus'] . ')',
        'Generated TSV native parity: ' . $generatedTsvNative['generatedNativeMatchCount']
            . '/' . $generatedTsvNative['sampleCount']
            . ' (' . $generatedTsvNative['parityStatus'] . ')',
        'CSV runner status: ' . $csv['runnerEvidence']['status'] . ' (executed: ' . ($csv['runnerEvidence']['executed'] ? 'yes' : 'no') . ')',
        'TSV runner status: ' . $tsv['runnerEvidence']['status'] . ' (executed: ' . ($tsv['runnerEvidence']['executed'] ? 'yes' : 'no') . ')',
    ];

    if ($report['validationIssues'] === []) {
        $lines[] = 'Validation issues: none';
    } else {
        $lines[] = 'Validation issues:';
        foreach ($report['validationIssues'] as $issue) {
            $lines[] = '- ' . $issue;
        }
    }

    return implode(PHP_EOL, $lines) . PHP_EOL;
};

try {
    $repoRoot = dirname(__DIR__);
    $json = false;
    $requireHonestDenominators = false;
    $requireGeneratedCsvNativeParity = false;
    $requireGeneratedTsvNativeParity = false;
    $requireRunnerNotRun = false;
    $requireNoValidationIssues = false;
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
        if ($arg === '--require-honest-denominators') {
            $requireHonestDenominators = true;
            continue;
        }
        if ($arg === '--require-generated-tsv-native-parity') {
            $requireGeneratedTsvNativeParity = true;
            continue;
        }
        if ($arg === '--require-generated-csv-native-parity') {
            $requireGeneratedCsvNativeParity = true;
            continue;
        }
        if ($arg === '--require-runner-not-run') {
            $requireRunnerNotRun = true;
            continue;
        }
        if ($arg === '--require-no-validation-issues') {
            $requireNoValidationIssues = true;
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

        throw new InvalidArgumentException("Unknown option: {$arg}");
    }
    if ($repoRoot === '') {
        throw new InvalidArgumentException('Repository root must not be empty');
    }

    $reader = new DelimitedTextReader();
    $csvPacket = $reader->readCsv("Fruit,Price\nApple,25 cents\n")->children[0]->attr('delimitedText');
    $tsvPacket = $reader->readTsv("Fruit\tPrice\nApple\t25 cents\n")->children[0]->attr('delimitedText');
    $csvEvidence = $csvPacket['upstreamEvidence'] ?? [];
    $tsvEvidence = $tsvPacket['upstreamEvidence'] ?? [];
    $denominatorIssues = $validateHonestDenominators($csvEvidence, $tsvEvidence);
    $runnerIssues = $validateRunnerNotRun($csvEvidence, $tsvEvidence);
    $generatedCsvNativeParity = DelimitedTextUpstreamReaderEvidence::generatedCsvNativeParityEvidence($repoRoot);
    $generatedCsvNativeIssues = $validateGeneratedCsvNativeParity($generatedCsvNativeParity);
    $generatedTsvNativeParity = DelimitedTextUpstreamReaderEvidence::generatedTsvNativeParityEvidence($repoRoot);
    $generatedTsvNativeIssues = $validateGeneratedTsvNativeParity($generatedTsvNativeParity);
    $report = [
        'tool' => 'pandoc-delimited-text-reader-evidence',
        'claim' => 'Native CSV/TSV reader evidence only; upstream Haskell runner is not executed.',
        'csv' => $csvEvidence,
        'tsv' => $tsvEvidence,
        'generatedCsvNativeParity' => $generatedCsvNativeParity,
        'generatedTsvNativeParity' => $generatedTsvNativeParity,
        'validation' => [
            'honestDenominators' => $denominatorIssues === [],
            'generatedCsvNativeParity' => $generatedCsvNativeIssues === [],
            'generatedTsvNativeParity' => $generatedTsvNativeIssues === [],
            'runnerNotRun' => $runnerIssues === [],
        ],
        'validationIssues' => [...$denominatorIssues, ...$generatedCsvNativeIssues, ...$generatedTsvNativeIssues, ...$runnerIssues],
    ];

    if ($json) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_THROW_ON_ERROR) . PHP_EOL);
    } else {
        fwrite(STDOUT, $formatTextReport($report));
    }

    if ($requireHonestDenominators && $denominatorIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: honest denominator validation reported issues\n");
        exit(1);
    }
    if ($requireGeneratedCsvNativeParity && $generatedCsvNativeIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: generated CSV native parity validation reported issues\n");
        exit(1);
    }
    if ($requireGeneratedTsvNativeParity && $generatedTsvNativeIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: generated TSV native parity validation reported issues\n");
        exit(1);
    }
    if ($requireRunnerNotRun && $runnerIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: runner not-run validation reported issues\n");
        exit(1);
    }
    if ($requireNoValidationIssues && $report['validationIssues'] !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: validation reported issues\n");
        exit(1);
    }

    exit(0);
} catch (InvalidArgumentException $exception) {
    fwrite(STDERR, 'pandoc-delimited-text-reader-evidence: ' . $exception->getMessage() . PHP_EOL);
    fwrite(STDERR, $usage() . PHP_EOL);
    exit(2);
} catch (Throwable $throwable) {
    fwrite(STDERR, 'pandoc-delimited-text-reader-evidence: ' . $throwable::class . ': ' . $throwable->getMessage() . PHP_EOL);
    exit(1);
}
