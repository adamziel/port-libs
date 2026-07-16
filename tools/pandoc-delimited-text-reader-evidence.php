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
  --require-parser-option-fixture-count[=N]
                                  Exit 1 unless the CSV parser-option fixture
                                  names and generated native matches are present.
  --require-generated-tsv-native-parity[=N]
                                  Exit 1 unless the generated TSV-to-native
                                  samples match their native fixtures.
  --require-current-csv-direct-native-parity[=N]
                                  Exit 1 unless the current CSV command
                                  transcripts match their embedded native output.
  --require-current-tsv-direct-native-parity[=N]
                                  Exit 1 unless the current TSV direct fixture
                                  matches its native output.
  --require-generated-csv-native-parity[=N]
                                  Exit 1 unless the generated CSV-to-native
                                  samples match their native fixtures.
  --require-pandoc-executable-csv-native-parity[=N]
                                  Exit 1 unless installed pandoc 3.10 matches
                                  the representable generated CSV subset.
  --require-pandoc-executable-tsv-native-parity[=N]
                                  Exit 1 unless installed pandoc 3.10 matches
                                  the representable generated TSV subset.
  --require-runner-not-run        Exit 1 unless upstream runner evidence is
                                  structured as not-run for CSV and TSV.
  --require-runner-plan           Exit 1 unless upstream runner evidence includes
                                  the pinned non-executed Command:/csv.md/#1 plan.
  --runner-result-artifact PATH   Validate a captured upstream runner result JSON artifact.
  --require-runner-result-artifact
                                  Exit 1 unless the supplied runner result artifact is valid.
  --require-no-validation-issues  Exit 1 when any validation issue is reported.
  --help                          Show this help.

This is a focused evidence gate for the native CSV/TSV reader packet.
It does not run Cabal/Tasty. It only executes pandoc when explicitly requested.
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
    $csvDirectFixtureCount = DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT;
    $expect(($csv['denominator'] ?? null) === $csvDirectFixtureCount, "CSV direct denominator must be {$csvDirectFixtureCount}");
    $expect(($csv['directFixtureDenominator'] ?? null) === $csvDirectFixtureCount, "CSV direct fixture denominator must be {$csvDirectFixtureCount}");
    $expect(($csv['directFixtureCount'] ?? null) === $csvDirectFixtureCount, "CSV direct fixture count must be {$csvDirectFixtureCount}");
    $expect(($csv['fixtures'] ?? null) === ($csv['directFixtures'] ?? null), 'CSV fixtures must be direct fixtures');
    $expect(($csv['csvDirectFixtureDenominator'] ?? null) === $csvDirectFixtureCount, "CSV direct fixture split denominator must be {$csvDirectFixtureCount}");
    $tsvDirectFixtureCount = DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT;
    $tsvDirectFixtures = DelimitedTextUpstreamReaderEvidence::tsvDirectFixturePaths();
    $expect(($csv['tsvDirectFixtureDenominator'] ?? null) === $tsvDirectFixtureCount, "TSV direct fixture split denominator must be {$tsvDirectFixtureCount}");
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
    $expect(($tsv['denominator'] ?? null) === $tsvDirectFixtureCount, "TSV direct denominator must be {$tsvDirectFixtureCount}");
    $expect(($tsv['directFixtureDenominator'] ?? null) === $tsvDirectFixtureCount, "TSV direct fixture denominator must be {$tsvDirectFixtureCount}");
    $expect(($tsv['directFixtureCount'] ?? null) === $tsvDirectFixtureCount, "TSV direct fixture count must be {$tsvDirectFixtureCount}");
    $expect(($tsv['fixtures'] ?? null) === $tsvDirectFixtures, 'TSV fixtures must match the pinned TSV direct command fixture list');
    $expect(($tsv['directFixtures'] ?? null) === $tsvDirectFixtures, 'TSV direct fixtures must match the pinned TSV direct command fixture list');
    $expect(($tsv['csvDirectFixtureDenominator'] ?? null) === $csvDirectFixtureCount, 'CSV direct fixture split denominator must remain visible from TSV evidence');
    $expect(($tsv['tsvDirectFixtureDenominator'] ?? null) === $tsvDirectFixtureCount, "TSV direct fixture split denominator must be {$tsvDirectFixtureCount}");
    $expect(($tsv['integrationFixtureCount'] ?? null) === 0, 'TSV integration fixture count must be 0');
    $expect(($tsv['adjacentFixtureEvidence'] ?? null) === [], 'TSV adjacent fixture evidence must be empty');

    return $issues;
};

$validateParserOptionFixtures = static function (array $csv, array $tsv, array $generatedCsvNativeParity, ?int $requiredFixtureCount = null): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $expectedFixtureCount = $requiredFixtureCount ?? DelimitedTextUpstreamReaderEvidence::EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT;
    $expectedFixtures = DelimitedTextUpstreamReaderEvidence::csvParserOptionFixtureNames();
    $samples = is_array($generatedCsvNativeParity['samples'] ?? null) ? $generatedCsvNativeParity['samples'] : [];
    $samplesByName = [];
    foreach ($samples as $sample) {
        if (is_array($sample) && is_string($sample['name'] ?? null)) {
            $samplesByName[$sample['name']] = $sample;
        }
    }

    $expect(($csv['reader'] ?? null) === 'csv', 'CSV parser-option fixture evidence reader must be csv');
    $expect(($csv['parserOptionFixtureCount'] ?? null) === $expectedFixtureCount, "CSV parser-option fixture count must be {$expectedFixtureCount}");
    $expect(($csv['parserOptionFixtures'] ?? null) === $expectedFixtures, 'CSV parser-option fixture names must match the pinned generated fixture list');
    $expect(count($expectedFixtures) === $expectedFixtureCount, "Pinned CSV parser-option fixture list must contain {$expectedFixtureCount} fixture names");
    $expect(($tsv['parserOptionFixtureCount'] ?? null) === 0, 'TSV parser-option fixture count must remain 0 because the current option fixture set is CSV-scoped');
    $expect(($tsv['parserOptionFixtures'] ?? null) === [], 'TSV parser-option fixtures must remain empty');
    $expect(($generatedCsvNativeParity['reader'] ?? null) === 'csv', 'Generated parser-option native parity evidence reader must be csv');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredCsvParserOptionFixtureEvidence($csv, $expectedFixtureCount),
        'CSV parser-option fixture helper must recognize required evidence'
    );
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvParserOptionNativeParity($generatedCsvNativeParity, $expectedFixtureCount),
        'Generated CSV parser-option native parity helper must recognize required evidence'
    );

    foreach ($expectedFixtures as $fixture) {
        $sample = is_array($samplesByName[$fixture] ?? null) ? $samplesByName[$fixture] : [];
        $expect($sample !== [], "CSV parser-option fixture {$fixture} must have a generated native parity sample");
        $expect(($sample['status'] ?? null) === 'matched', "CSV parser-option fixture {$fixture} must match its native fixture");
        $expect(($sample['reader'] ?? null) === 'csv', "CSV parser-option fixture {$fixture} must be read as csv");
        $expect(($sample['staticFixtureBindingStatus'] ?? null) === 'valid-generated-csv-native-sample-static-binding', "CSV parser-option fixture {$fixture} must have a valid static fixture binding");
    }

    return $issues;
};

$validateGeneratedTsvNativeParity = static function (array $evidence, ?int $requiredSampleCount = null): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $expectedSampleCount = $requiredSampleCount ?? DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_NATIVE_SAMPLE_COUNT;
    $expectedBindingStatus = 'valid-generated-tsv-native-sample-static-binding';
    $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

    $expect(($evidence['reader'] ?? null) === 'tsv', 'Generated TSV native parity evidence reader must be tsv');
    $expect(($evidence['tsvDirectFixtureDenominator'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT, 'Generated TSV native parity must expose the current TSV direct denominator');
    $expect(($evidence['sampleCount'] ?? null) === $expectedSampleCount, 'Generated TSV native parity sample count must match expected generated sample count');
    $expect(count($samples) === $expectedSampleCount, 'Generated TSV native parity sample result count must match expected generated sample count');
    $expect(($evidence['comparedSampleCount'] ?? null) === $expectedSampleCount, 'Generated TSV native parity compared sample count must match expected generated sample count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Generated TSV native parity parse failure count must be 0');
    $expect(($evidence['generatedNativeMatchCount'] ?? null) === $expectedSampleCount, 'Generated TSV native parity match count must match expected generated sample count');
    $expect(($evidence['generatedNativeMismatchCount'] ?? null) === 0, 'Generated TSV native parity mismatch count must be 0');
    $expect(($evidence['staticFixtureBindingValidCount'] ?? null) === $expectedSampleCount, 'Generated TSV native parity static fixture binding valid count must match expected generated sample count');
    $expect(($evidence['staticFixtureBindingInvalidCount'] ?? null) === 0, 'Generated TSV native parity static fixture binding invalid count must be 0');
    $expect(array_column($samples, 'staticFixtureBindingStatus') === array_fill(0, $expectedSampleCount, $expectedBindingStatus), 'Generated TSV native parity sample static fixture bindings must be valid');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvNativeParity($evidence, $expectedSampleCount),
        'Generated TSV native parity helper must recognize required evidence'
    );

    return $issues;
};

$validateCurrentCsvDirectNativeParity = static function (array $evidence, ?int $requiredSampleCount = null): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $expectedSampleCount = $requiredSampleCount ?? DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CURRENT_CSV_DIRECT_NATIVE_PAIR_COUNT;
    $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

    $expect(($evidence['reader'] ?? null) === 'csv', 'Current CSV direct native parity evidence reader must be csv');
    $expect(($evidence['csvDirectFixtureDenominator'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT, 'Current CSV direct native parity must keep CSV upstream direct denominator at 3');
    $expect(($evidence['currentCsvDirectNativePairCount'] ?? null) === $expectedSampleCount, 'Current CSV direct native parity pair count must match expected current sample count');
    $expect(($evidence['sampleCount'] ?? null) === $expectedSampleCount, 'Current CSV direct native parity sample count must match expected current sample count');
    $expect(count($samples) === $expectedSampleCount, 'Current CSV direct native parity sample result count must match expected current sample count');
    $expect(($evidence['comparedSampleCount'] ?? null) === $expectedSampleCount, 'Current CSV direct native parity compared sample count must match expected current sample count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Current CSV direct native parity parse failure count must be 0');
    $expect(($evidence['currentCsvDirectNativeMatchCount'] ?? null) === $expectedSampleCount, 'Current CSV direct native parity match count must match expected current sample count');
    $expect(($evidence['currentCsvDirectNativeMismatchCount'] ?? null) === 0, 'Current CSV direct native parity mismatch count must be 0');
    $expect(array_column($samples, 'status') === array_fill(0, $expectedSampleCount, 'matched'), 'Current CSV direct native parity samples must be matched');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredCurrentCsvDirectNativeParity($evidence, $expectedSampleCount),
        'Current CSV direct native parity helper must recognize required evidence'
    );

    return $issues;
};

$validateCurrentTsvDirectNativeParity = static function (array $evidence, ?int $requiredSampleCount = null): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $expectedSampleCount = $requiredSampleCount ?? DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CURRENT_TSV_DIRECT_NATIVE_PAIR_COUNT;
    $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

    $expect(($evidence['reader'] ?? null) === 'tsv', 'Current TSV direct native parity evidence reader must be tsv');
    $expect(($evidence['tsvDirectFixtureDenominator'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT, 'Current TSV direct native parity must expose the current TSV upstream direct denominator');
    $expect(($evidence['currentTsvDirectNativePairCount'] ?? null) === $expectedSampleCount, 'Current TSV direct native parity pair count must match expected current sample count');
    $expect(($evidence['sampleCount'] ?? null) === $expectedSampleCount, 'Current TSV direct native parity sample count must match expected current sample count');
    $expect(count($samples) === $expectedSampleCount, 'Current TSV direct native parity sample result count must match expected current sample count');
    $expect(($evidence['comparedSampleCount'] ?? null) === $expectedSampleCount, 'Current TSV direct native parity compared sample count must match expected current sample count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Current TSV direct native parity parse failure count must be 0');
    $expect(($evidence['currentTsvDirectNativeMatchCount'] ?? null) === $expectedSampleCount, 'Current TSV direct native parity match count must match expected current sample count');
    $expect(($evidence['currentTsvDirectNativeMismatchCount'] ?? null) === 0, 'Current TSV direct native parity mismatch count must be 0');
    $expect(array_column($samples, 'status') === array_fill(0, $expectedSampleCount, 'matched'), 'Current TSV direct native parity samples must be matched');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredCurrentTsvDirectNativeParity($evidence, $expectedSampleCount),
        'Current TSV direct native parity helper must recognize required evidence'
    );

    return $issues;
};

$validateGeneratedCsvNativeParity = static function (array $evidence, ?int $requiredSampleCount = null): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $expectedSampleCount = $requiredSampleCount ?? DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_NATIVE_SAMPLE_COUNT;
    $expectedBindingStatus = 'valid-generated-csv-native-sample-static-binding';
    $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

    $expect(($evidence['reader'] ?? null) === 'csv', 'Generated CSV native parity evidence reader must be csv');
    $expect(($evidence['csvDirectFixtureDenominator'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT, 'Generated CSV native parity must keep the current CSV direct denominator');
    $expect(($evidence['sampleCount'] ?? null) === $expectedSampleCount, 'Generated CSV native parity sample count must match expected generated sample count');
    $expect(count($samples) === $expectedSampleCount, 'Generated CSV native parity sample result count must match expected generated sample count');
    $expect(($evidence['comparedSampleCount'] ?? null) === $expectedSampleCount, 'Generated CSV native parity compared sample count must match expected generated sample count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Generated CSV native parity parse failure count must be 0');
    $expect(($evidence['generatedNativeMatchCount'] ?? null) === $expectedSampleCount, 'Generated CSV native parity match count must match expected generated sample count');
    $expect(($evidence['generatedNativeMismatchCount'] ?? null) === 0, 'Generated CSV native parity mismatch count must be 0');
    $expect(($evidence['staticFixtureBindingValidCount'] ?? null) === $expectedSampleCount, 'Generated CSV native parity static fixture binding valid count must match expected generated sample count');
    $expect(($evidence['staticFixtureBindingInvalidCount'] ?? null) === 0, 'Generated CSV native parity static fixture binding invalid count must be 0');
    $expect(array_column($samples, 'staticFixtureBindingStatus') === array_fill(0, $expectedSampleCount, $expectedBindingStatus), 'Generated CSV native parity sample static fixture bindings must be valid');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvNativeParity($evidence, $expectedSampleCount),
        'Generated CSV native parity helper must recognize required evidence'
    );

    return $issues;
};

$validateGeneratedCsvPandocExecutableNativeParity = static function (array $evidence, ?int $requiredSampleCount = null): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $expectedSampleCount = $requiredSampleCount ?? DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_CSV_PANDOC_EXECUTABLE_NATIVE_SAMPLE_COUNT;
    $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

    $expect(($evidence['reader'] ?? null) === 'csv', 'Pandoc executable CSV native parity evidence reader must be csv');
    $expect(($evidence['requiredPandocVersion'] ?? null) === DelimitedTextUpstreamReaderEvidence::REQUIRED_PANDOC_EXECUTABLE_VERSION, 'Pandoc executable CSV native parity must require pandoc 3.10');
    $expect(($evidence['pandocVersion'] ?? null) === DelimitedTextUpstreamReaderEvidence::REQUIRED_PANDOC_EXECUTABLE_VERSION, 'Pandoc executable CSV native parity must observe pandoc 3.10');
    $expect(($evidence['pandocExecutableStatus'] ?? null) === 'available', 'Pandoc executable CSV native parity must have an available pandoc executable');
    $expect(($evidence['csvDirectFixtureDenominator'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_CSV_DIRECT_FIXTURE_COUNT, 'Pandoc executable CSV native parity must keep the current CSV direct denominator');
    $expect(($evidence['sampleCount'] ?? null) === $expectedSampleCount, 'Pandoc executable CSV native parity sample count must match expected generated subset count');
    $expect(count($samples) === $expectedSampleCount, 'Pandoc executable CSV native parity sample result count must match expected generated subset count');
    $expect(($evidence['comparedSampleCount'] ?? null) === $expectedSampleCount, 'Pandoc executable CSV native parity compared sample count must match expected generated subset count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Pandoc executable CSV native parity parse failure count must be 0');
    $expect(($evidence['pandocExecutableNativeMatchCount'] ?? null) === $expectedSampleCount, 'Pandoc executable CSV native parity match count must match expected generated subset count');
    $expect(($evidence['pandocExecutableNativeMismatchCount'] ?? null) === 0, 'Pandoc executable CSV native parity mismatch count must be 0');
    $expect(($evidence['staticFixtureBindingValidCount'] ?? null) === $expectedSampleCount, 'Pandoc executable CSV native parity static fixture binding valid count must match expected generated subset count');
    $expect(($evidence['staticFixtureBindingInvalidCount'] ?? null) === 0, 'Pandoc executable CSV native parity static fixture binding invalid count must be 0');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvPandocExecutableNativeParity($evidence, $expectedSampleCount),
        'Pandoc executable CSV native parity helper must recognize required evidence'
    );

    return $issues;
};

$validateGeneratedTsvPandocExecutableNativeParity = static function (array $evidence, ?int $requiredSampleCount = null): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };
    $expectedSampleCount = $requiredSampleCount ?? DelimitedTextUpstreamReaderEvidence::EXPECTED_GENERATED_TSV_PANDOC_EXECUTABLE_NATIVE_SAMPLE_COUNT;
    $samples = is_array($evidence['samples'] ?? null) ? $evidence['samples'] : [];

    $expect(($evidence['reader'] ?? null) === 'tsv', 'Pandoc executable TSV native parity evidence reader must be tsv');
    $expect(($evidence['requiredPandocVersion'] ?? null) === DelimitedTextUpstreamReaderEvidence::REQUIRED_PANDOC_EXECUTABLE_VERSION, 'Pandoc executable TSV native parity must require pandoc 3.10');
    $expect(($evidence['pandocVersion'] ?? null) === DelimitedTextUpstreamReaderEvidence::REQUIRED_PANDOC_EXECUTABLE_VERSION, 'Pandoc executable TSV native parity must observe pandoc 3.10');
    $expect(($evidence['pandocExecutableStatus'] ?? null) === 'available', 'Pandoc executable TSV native parity must have an available pandoc executable');
    $expect(($evidence['tsvDirectFixtureDenominator'] ?? null) === DelimitedTextUpstreamReaderEvidence::EXPECTED_STATIC_TSV_DIRECT_FIXTURE_COUNT, 'Pandoc executable TSV native parity must expose the current TSV direct denominator');
    $expect(($evidence['sampleCount'] ?? null) === $expectedSampleCount, 'Pandoc executable TSV native parity sample count must match expected generated subset count');
    $expect(count($samples) === $expectedSampleCount, 'Pandoc executable TSV native parity sample result count must match expected generated subset count');
    $expect(($evidence['comparedSampleCount'] ?? null) === $expectedSampleCount, 'Pandoc executable TSV native parity compared sample count must match expected generated subset count');
    $expect(($evidence['parseFailureCount'] ?? null) === 0, 'Pandoc executable TSV native parity parse failure count must be 0');
    $expect(($evidence['pandocExecutableNativeMatchCount'] ?? null) === $expectedSampleCount, 'Pandoc executable TSV native parity match count must match expected generated subset count');
    $expect(($evidence['pandocExecutableNativeMismatchCount'] ?? null) === 0, 'Pandoc executable TSV native parity mismatch count must be 0');
    $expect(($evidence['staticFixtureBindingValidCount'] ?? null) === $expectedSampleCount, 'Pandoc executable TSV native parity static fixture binding valid count must match expected generated subset count');
    $expect(($evidence['staticFixtureBindingInvalidCount'] ?? null) === 0, 'Pandoc executable TSV native parity static fixture binding invalid count must be 0');
    $expect(
        DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvPandocExecutableNativeParity($evidence, $expectedSampleCount),
        'Pandoc executable TSV native parity helper must recognize required evidence'
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

$validateRunnerPlan = static function (array $csv, array $tsv): array {
    $issues = [];
    $expect = static function (bool $condition, string $message) use (&$issues): void {
        if (!$condition) {
            $issues[] = $message;
        }
    };

    foreach (['CSV' => $csv, 'TSV' => $tsv] as $label => $evidence) {
        $runner = is_array($evidence['runnerEvidence'] ?? null) ? $evidence['runnerEvidence'] : [];
        $target = is_array($runner['target'] ?? null) ? $runner['target'] : [];
        $commandPlan = is_array($runner['commandPlan'] ?? null) ? $runner['commandPlan'] : [];
        $executionBoundary = is_array($runner['executionBoundary'] ?? null) ? $runner['executionBoundary'] : [];

        $expect(
            DelimitedTextUpstreamReaderEvidence::hasRunnerPlanEvidence($runner),
            "{$label} runner command-plan evidence must match the pinned Command:/csv.md/#1 target"
        );
        $expect(($runner['commandPlanStatus'] ?? null) === 'planned-not-run', "{$label} runner command plan must be planned-not-run");
        $expect(($target['tastyGroupPath'] ?? null) === ['Command:', 'csv.md', '#1'], "{$label} runner target must be Command:/csv.md/#1");
        $expect(($target['tastyPattern'] ?? null) === '$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', "{$label} runner pattern must target the csv.md command fixture");
        $expect(($target['directCommandFixture'] ?? null) === 'test/command/csv.md', "{$label} runner target must name the direct csv.md command fixture");
        $expect(($target['directInputFixture'] ?? null) === 'test/command/01.csv', "{$label} runner target must name the direct 01.csv input fixture");
        $expect(($commandPlan['kind'] ?? null) === 'upstream-runner-command-plan', "{$label} runner command plan kind must be explicit");
        $expect(($commandPlan['status'] ?? null) === 'planned-not-run', "{$label} runner command plan status must be planned-not-run");
        $expect(($commandPlan['workingDirectory'] ?? null) === 'hydrated Pandoc upstream checkout root', "{$label} runner command plan must identify the upstream checkout working directory");
        $expect(($commandPlan['networkMode'] ?? null) === 'offline', "{$label} runner command plan must stay offline");
        $expect(($commandPlan['commandCount'] ?? null) === 3, "{$label} runner command plan must include dependency, list, and run commands");
        $expect(($executionBoundary['kind'] ?? null) === 'upstream-runner-non-execution-boundary', "{$label} runner execution boundary kind must be explicit");
        $expect(($executionBoundary['status'] ?? null) === 'plan-only-not-run', "{$label} runner execution boundary status must be plan-only-not-run");
        $expect(($executionBoundary['planOnly'] ?? null) === true, "{$label} runner execution boundary must be plan-only");
        $expect(($executionBoundary['executedCommandCount'] ?? null) === 0, "{$label} runner execution boundary must report zero executed commands");
        $expect(($executionBoundary['executedCommands'] ?? null) === [], "{$label} runner execution boundary must not contain executed commands");
        $expect(($executionBoundary['upstreamRunnerParityClaimed'] ?? null) === false, "{$label} runner execution boundary must not claim upstream runner parity");
        $expect(in_array('.port-libs/pandoc-runner/logs/delimited-text-targeted-run.txt', $runner['requiredTranscripts'] ?? [], true), "{$label} runner plan must require the targeted run transcript");
        $expect(in_array('.port-libs/pandoc-runner/artifacts/delimited-text-targeted-run/result.json', $runner['requiredArtifacts'] ?? [], true), "{$label} runner plan must require the targeted result artifact path");
    }

    return $issues;
};

$formatTextReport = static function (array $report): string {
    $csv = $report['csv'];
    $tsv = $report['tsv'];
    $generatedCsvNative = $report['generatedCsvNativeParity'];
    $generatedTsvNative = $report['generatedTsvNativeParity'];
    $currentCsvDirectNative = $report['currentCsvDirectNativeParity'];
    $currentTsvDirectNative = $report['currentTsvDirectNativeParity'];
    $generatedCsvPandocExecutableNative = is_array($report['generatedCsvPandocExecutableNativeParity'] ?? null)
        ? $report['generatedCsvPandocExecutableNativeParity']
        : null;
    $generatedTsvPandocExecutableNative = is_array($report['generatedTsvPandocExecutableNativeParity'] ?? null)
        ? $report['generatedTsvPandocExecutableNativeParity']
        : null;
    $runnerResultArtifact = is_array($report['runnerResultArtifactEvidence'] ?? null) ? $report['runnerResultArtifactEvidence'] : [];
    $runnerResultArtifactValidation = is_array($runnerResultArtifact['validation'] ?? null) ? $runnerResultArtifact['validation'] : [];
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
        'Current CSV direct native parity: ' . $currentCsvDirectNative['currentCsvDirectNativeMatchCount']
            . '/' . $currentCsvDirectNative['sampleCount']
            . ' (' . $currentCsvDirectNative['parityStatus'] . ')',
        'Current TSV direct native parity: ' . $currentTsvDirectNative['currentTsvDirectNativeMatchCount']
            . '/' . $currentTsvDirectNative['sampleCount']
            . ' (' . $currentTsvDirectNative['parityStatus'] . ')',
        'CSV parser-option fixtures: ' . $csv['parserOptionFixtureCount']
            . '/' . DelimitedTextUpstreamReaderEvidence::EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT,
        'CSV runner status: ' . $csv['runnerEvidence']['status'] . ' (executed: ' . ($csv['runnerEvidence']['executed'] ? 'yes' : 'no') . ')',
        'CSV runner plan: ' . ($csv['runnerEvidence']['commandPlanStatus'] ?? 'unknown'),
        'CSV runner boundary: ' . ($csv['runnerEvidence']['executionBoundary']['status'] ?? 'unknown'),
        'TSV runner status: ' . $tsv['runnerEvidence']['status'] . ' (executed: ' . ($tsv['runnerEvidence']['executed'] ? 'yes' : 'no') . ')',
        'TSV runner plan: ' . ($tsv['runnerEvidence']['commandPlanStatus'] ?? 'unknown'),
        'TSV runner boundary: ' . ($tsv['runnerEvidence']['executionBoundary']['status'] ?? 'unknown'),
        'Runner result artifact: ' . (string) (($runnerResultArtifactValidation['status'] ?? null) ?? 'not-supplied'),
    ];
    if ($generatedCsvPandocExecutableNative !== null) {
        $lines[] = 'Pandoc executable CSV native parity: '
            . $generatedCsvPandocExecutableNative['pandocExecutableNativeMatchCount']
            . '/' . $generatedCsvPandocExecutableNative['sampleCount']
            . ' (' . $generatedCsvPandocExecutableNative['parityStatus'] . ')';
    }
    if ($generatedTsvPandocExecutableNative !== null) {
        $lines[] = 'Pandoc executable TSV native parity: '
            . $generatedTsvPandocExecutableNative['pandocExecutableNativeMatchCount']
            . '/' . $generatedTsvPandocExecutableNative['sampleCount']
            . ' (' . $generatedTsvPandocExecutableNative['parityStatus'] . ')';
    }

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
    $requireParserOptionFixtures = false;
    $requireGeneratedCsvNativeParity = false;
    $requireGeneratedTsvNativeParity = false;
    $requireCurrentCsvDirectNativeParity = false;
    $requireCurrentTsvDirectNativeParity = false;
    $requireGeneratedCsvPandocExecutableNativeParity = false;
    $requireGeneratedTsvPandocExecutableNativeParity = false;
    $requiredGeneratedCsvNativeParityCount = null;
    $requiredGeneratedTsvNativeParityCount = null;
    $requiredCurrentCsvDirectNativeParityCount = null;
    $requiredCurrentTsvDirectNativeParityCount = null;
    $requiredParserOptionFixtureCount = null;
    $requiredGeneratedCsvPandocExecutableNativeParityCount = null;
    $requiredGeneratedTsvPandocExecutableNativeParityCount = null;
    $requireRunnerNotRun = false;
    $requireRunnerPlan = false;
    $runnerResultArtifact = null;
    $requireRunnerResultArtifact = false;
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
        $parseNonNegativeInt = static function (string $name, string $value): int {
            if ($value === '' || preg_match('/^\d+$/', $value) !== 1) {
                throw new InvalidArgumentException("{$name} must be a non-negative integer");
            }

            return (int) $value;
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
        if ($arg === '--require-parser-option-fixture-count') {
            $requireParserOptionFixtures = true;
            continue;
        }
        if (str_starts_with($arg, '--require-parser-option-fixture-count=')) {
            $requireParserOptionFixtures = true;
            $requiredParserOptionFixtureCount = $parseNonNegativeInt(
                '--require-parser-option-fixture-count',
                substr($arg, strlen('--require-parser-option-fixture-count='))
            );
            continue;
        }
        if ($arg === '--require-generated-tsv-native-parity') {
            $requireGeneratedTsvNativeParity = true;
            continue;
        }
        if (str_starts_with($arg, '--require-generated-tsv-native-parity=')) {
            $requireGeneratedTsvNativeParity = true;
            $requiredGeneratedTsvNativeParityCount = $parseNonNegativeInt(
                '--require-generated-tsv-native-parity',
                substr($arg, strlen('--require-generated-tsv-native-parity='))
            );
            continue;
        }
        if ($arg === '--require-current-csv-direct-native-parity') {
            $requireCurrentCsvDirectNativeParity = true;
            continue;
        }
        if (str_starts_with($arg, '--require-current-csv-direct-native-parity=')) {
            $requireCurrentCsvDirectNativeParity = true;
            $requiredCurrentCsvDirectNativeParityCount = $parseNonNegativeInt(
                '--require-current-csv-direct-native-parity',
                substr($arg, strlen('--require-current-csv-direct-native-parity='))
            );
            continue;
        }
        if ($arg === '--require-current-tsv-direct-native-parity') {
            $requireCurrentTsvDirectNativeParity = true;
            continue;
        }
        if (str_starts_with($arg, '--require-current-tsv-direct-native-parity=')) {
            $requireCurrentTsvDirectNativeParity = true;
            $requiredCurrentTsvDirectNativeParityCount = $parseNonNegativeInt(
                '--require-current-tsv-direct-native-parity',
                substr($arg, strlen('--require-current-tsv-direct-native-parity='))
            );
            continue;
        }
        if ($arg === '--require-generated-csv-native-parity') {
            $requireGeneratedCsvNativeParity = true;
            continue;
        }
        if (str_starts_with($arg, '--require-generated-csv-native-parity=')) {
            $requireGeneratedCsvNativeParity = true;
            $requiredGeneratedCsvNativeParityCount = $parseNonNegativeInt(
                '--require-generated-csv-native-parity',
                substr($arg, strlen('--require-generated-csv-native-parity='))
            );
            continue;
        }
        if ($arg === '--require-pandoc-executable-csv-native-parity') {
            $requireGeneratedCsvPandocExecutableNativeParity = true;
            continue;
        }
        if (str_starts_with($arg, '--require-pandoc-executable-csv-native-parity=')) {
            $requireGeneratedCsvPandocExecutableNativeParity = true;
            $requiredGeneratedCsvPandocExecutableNativeParityCount = $parseNonNegativeInt(
                '--require-pandoc-executable-csv-native-parity',
                substr($arg, strlen('--require-pandoc-executable-csv-native-parity='))
            );
            continue;
        }
        if ($arg === '--require-pandoc-executable-tsv-native-parity') {
            $requireGeneratedTsvPandocExecutableNativeParity = true;
            continue;
        }
        if (str_starts_with($arg, '--require-pandoc-executable-tsv-native-parity=')) {
            $requireGeneratedTsvPandocExecutableNativeParity = true;
            $requiredGeneratedTsvPandocExecutableNativeParityCount = $parseNonNegativeInt(
                '--require-pandoc-executable-tsv-native-parity',
                substr($arg, strlen('--require-pandoc-executable-tsv-native-parity='))
            );
            continue;
        }
        if ($arg === '--require-runner-not-run') {
            $requireRunnerNotRun = true;
            continue;
        }
        if ($arg === '--require-runner-plan') {
            $requireRunnerPlan = true;
            continue;
        }
        if ($arg === '--require-runner-result-artifact') {
            $requireRunnerResultArtifact = true;
            continue;
        }
        if ($arg === '--require-no-validation-issues') {
            $requireNoValidationIssues = true;
            continue;
        }
        if ($arg === '--runner-result-artifact') {
            $runnerResultArtifact = $nextValue('--runner-result-artifact');
            continue;
        }
        if (str_starts_with($arg, '--runner-result-artifact=')) {
            $runnerResultArtifact = substr($arg, strlen('--runner-result-artifact='));
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
    if ($runnerResultArtifact === '') {
        throw new InvalidArgumentException('Runner result artifact must not be empty');
    }

    $reader = new DelimitedTextReader();
    $csvPacket = $reader->readCsv("Fruit,Price\nApple,25 cents\n")->children[0]->attr('delimitedText');
    $tsvPacket = $reader->readTsv("Fruit\tPrice\nApple\t25 cents\n")->children[0]->attr('delimitedText');
    $csvEvidence = $csvPacket['upstreamEvidence'] ?? [];
    $tsvEvidence = $tsvPacket['upstreamEvidence'] ?? [];
    $denominatorIssues = $validateHonestDenominators($csvEvidence, $tsvEvidence);
    $runnerIssues = $validateRunnerNotRun($csvEvidence, $tsvEvidence);
    $generatedCsvNativeParity = DelimitedTextUpstreamReaderEvidence::generatedCsvNativeParityEvidence($repoRoot);
    $generatedCsvNativeIssues = $validateGeneratedCsvNativeParity($generatedCsvNativeParity, $requiredGeneratedCsvNativeParityCount);
    $parserOptionFixtureIssues = $validateParserOptionFixtures($csvEvidence, $tsvEvidence, $generatedCsvNativeParity, $requiredParserOptionFixtureCount);
    $generatedTsvNativeParity = DelimitedTextUpstreamReaderEvidence::generatedTsvNativeParityEvidence($repoRoot);
    $generatedTsvNativeIssues = $validateGeneratedTsvNativeParity($generatedTsvNativeParity, $requiredGeneratedTsvNativeParityCount);
    $currentCsvDirectNativeParity = DelimitedTextUpstreamReaderEvidence::currentCsvDirectNativeParityEvidence($repoRoot);
    $currentCsvDirectNativeIssues = $validateCurrentCsvDirectNativeParity($currentCsvDirectNativeParity, $requiredCurrentCsvDirectNativeParityCount);
    $currentTsvDirectNativeParity = DelimitedTextUpstreamReaderEvidence::currentTsvDirectNativeParityEvidence($repoRoot);
    $currentTsvDirectNativeIssues = $validateCurrentTsvDirectNativeParity($currentTsvDirectNativeParity, $requiredCurrentTsvDirectNativeParityCount);
    $generatedCsvPandocExecutableNativeParity = null;
    $generatedCsvPandocExecutableNativeIssues = [];
    if ($requireGeneratedCsvPandocExecutableNativeParity) {
        $generatedCsvPandocExecutableNativeParity = DelimitedTextUpstreamReaderEvidence::generatedCsvPandocExecutableNativeParityEvidence($repoRoot);
        $generatedCsvPandocExecutableNativeIssues = $validateGeneratedCsvPandocExecutableNativeParity(
            $generatedCsvPandocExecutableNativeParity,
            $requiredGeneratedCsvPandocExecutableNativeParityCount
        );
    }
    $generatedTsvPandocExecutableNativeParity = null;
    $generatedTsvPandocExecutableNativeIssues = [];
    if ($requireGeneratedTsvPandocExecutableNativeParity) {
        $generatedTsvPandocExecutableNativeParity = DelimitedTextUpstreamReaderEvidence::generatedTsvPandocExecutableNativeParityEvidence($repoRoot);
        $generatedTsvPandocExecutableNativeIssues = $validateGeneratedTsvPandocExecutableNativeParity(
            $generatedTsvPandocExecutableNativeParity,
            $requiredGeneratedTsvPandocExecutableNativeParityCount
        );
    }
    $runnerPlanIssues = $validateRunnerPlan($csvEvidence, $tsvEvidence);
    $runnerResultArtifactEvidence = null;
    $runnerResultArtifactIssues = [];
    if ($runnerResultArtifact !== null) {
        $artifactReport = (new DelimitedTextUpstreamReaderEvidence(
            $repoRoot,
            DelimitedTextUpstreamReaderEvidence::DEFAULT_RELATIVE_UPSTREAM_ROOT,
            $runnerResultArtifact
        ))->report();
        $runnerResultArtifactEvidence = is_array($artifactReport['runnerEvidence'] ?? null)
            ? $artifactReport['runnerEvidence']
            : [];
        if (!DelimitedTextUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($artifactReport)) {
            $runnerResultArtifactIssues[] = 'Runner result artifact evidence must be valid';
        }
    } elseif ($requireRunnerResultArtifact) {
        $runnerResultArtifactIssues[] = 'Runner result artifact evidence must be supplied';
    }

    $report = [
        'tool' => 'pandoc-delimited-text-reader-evidence',
        'claim' => $runnerResultArtifact === null
            ? 'Native CSV/TSV reader evidence only; upstream Haskell runner is not executed.'
            : 'Native CSV/TSV reader evidence plus a separately validated upstream Haskell runner result artifact.',
        'csv' => $csvEvidence,
        'tsv' => $tsvEvidence,
        'generatedCsvNativeParity' => $generatedCsvNativeParity,
        'generatedTsvNativeParity' => $generatedTsvNativeParity,
        'currentCsvDirectNativeParity' => $currentCsvDirectNativeParity,
        'currentTsvDirectNativeParity' => $currentTsvDirectNativeParity,
        'generatedCsvPandocExecutableNativeParity' => $generatedCsvPandocExecutableNativeParity,
        'generatedTsvPandocExecutableNativeParity' => $generatedTsvPandocExecutableNativeParity,
        'runnerResultArtifactEvidence' => $runnerResultArtifactEvidence,
        'validation' => [
            'honestDenominators' => $denominatorIssues === [],
            'parserOptionFixtures' => $parserOptionFixtureIssues === [],
            'generatedCsvNativeParity' => $generatedCsvNativeIssues === [],
            'generatedTsvNativeParity' => $generatedTsvNativeIssues === [],
            'currentCsvDirectNativeParity' => $currentCsvDirectNativeIssues === [],
            'currentTsvDirectNativeParity' => $currentTsvDirectNativeIssues === [],
            'generatedCsvPandocExecutableNativeParity' => $requireGeneratedCsvPandocExecutableNativeParity ? $generatedCsvPandocExecutableNativeIssues === [] : null,
            'generatedTsvPandocExecutableNativeParity' => $requireGeneratedTsvPandocExecutableNativeParity ? $generatedTsvPandocExecutableNativeIssues === [] : null,
            'runnerNotRun' => $runnerIssues === [],
            'runnerPlan' => $runnerPlanIssues === [],
            'runnerResultArtifact' => $runnerResultArtifact === null && !$requireRunnerResultArtifact ? null : $runnerResultArtifactIssues === [],
        ],
        'validationIssues' => [
            ...$denominatorIssues,
            ...$parserOptionFixtureIssues,
            ...$generatedCsvNativeIssues,
            ...$generatedTsvNativeIssues,
            ...$currentCsvDirectNativeIssues,
            ...$currentTsvDirectNativeIssues,
            ...$generatedCsvPandocExecutableNativeIssues,
            ...$generatedTsvPandocExecutableNativeIssues,
            ...$runnerIssues,
            ...$runnerPlanIssues,
            ...$runnerResultArtifactIssues,
        ],
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
    if ($requireParserOptionFixtures && $parserOptionFixtureIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: parser-option fixture validation reported issues\n");
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
    if ($requireCurrentCsvDirectNativeParity && $currentCsvDirectNativeIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: current CSV direct native parity validation reported issues\n");
        exit(1);
    }
    if ($requireCurrentTsvDirectNativeParity && $currentTsvDirectNativeIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: current TSV direct native parity validation reported issues\n");
        exit(1);
    }
    if ($requireGeneratedCsvPandocExecutableNativeParity && $generatedCsvPandocExecutableNativeIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: pandoc executable CSV native parity validation reported issues\n");
        exit(1);
    }
    if ($requireGeneratedTsvPandocExecutableNativeParity && $generatedTsvPandocExecutableNativeIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: pandoc executable TSV native parity validation reported issues\n");
        exit(1);
    }
    if ($requireRunnerNotRun && $runnerIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: runner not-run validation reported issues\n");
        exit(1);
    }
    if ($requireRunnerPlan && $runnerPlanIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: runner command-plan validation reported issues\n");
        exit(1);
    }
    if ($requireRunnerResultArtifact && $runnerResultArtifactIssues !== []) {
        fwrite(STDERR, "pandoc-delimited-text-reader-evidence: runner result artifact evidence is invalid\n");
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
