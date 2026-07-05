<?php

declare(strict_types=1);

use PortLibs\Pandoc\DelimitedTextReader;
use PortLibs\Pandoc\DelimitedTextUpstreamReaderEvidence;

$repoRoot = dirname(__DIR__, 3);

$readText = static function (string $relativePath) use ($repoRoot): string {
    $path = $repoRoot . DIRECTORY_SEPARATOR . $relativePath;
    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException("Unable to read {$relativePath}");
    }

    return $contents;
};

$readJson = static function (string $relativePath) use ($readText): array {
    $decoded = json_decode($readText($relativePath), true);
    if (!is_array($decoded)) {
        throw new RuntimeException("Unable to decode {$relativePath}: " . json_last_error_msg());
    }

    return $decoded;
};

return [
    'keeps delimited text reader parity rollup synced with live gates' =>
        static function (TestRunner $t) use ($repoRoot, $readJson, $readText): void {
            $manifest = $readJson('lanes/pandoc/UPSTREAM_TEST_MANIFEST.json');
            $laneStatus = $readJson('lanes/pandoc/lane-status.json');
            $workflow = $readText('.github/workflows/pandoc-html-delimited.yml');
            $runnerWorkflow = $readText('.github/workflows/pandoc-reader-runners.yml');

            $rollup = $manifest['delimitedTextReaderEvidence'] ?? null;
            $t->true(is_array($rollup), 'UPSTREAM_TEST_MANIFEST.json must carry delimited text reader evidence');
            $t->same('tracked-current-delimited-text-reader-parity-rollup', $rollup['status'] ?? null);
            $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $rollup['upstreamCommit'] ?? null);
            $t->contains('test/command/8661.md', (string) ($rollup['claim'] ?? ''));
            $t->contains('does not claim GFM pipe-table writer output', (string) ($rollup['claim'] ?? ''));
            $t->contains('not an upstream Haskell/Cabal/Tasty runner result', (string) ($rollup['claim'] ?? ''));
            $t->contains('does not claim full CSV/TSV parity', (string) ($rollup['claim'] ?? ''));

            $statusRollup = $laneStatus['delimitedTextReaderEvidenceStatus'] ?? null;
            $t->true(is_array($statusRollup), 'lane-status.json must carry delimited text reader evidence status');
            $t->same('first-direct-tsv-command-fixture-backed-by-native-reader-pair', $statusRollup['status'] ?? null);
            $t->same('test/command/8661.md', $statusRollup['upstreamFixture'] ?? null);
            $t->same('lanes/pandoc/fixtures/upstream-current-tsv-reader/8661.md', $statusRollup['checkedInFixture'] ?? null);
            $t->true(in_array('GFM pipe-table writer output for test/command/8661.md', $statusRollup['doesNotAssert'] ?? [], true));
            $t->true(in_array('upstream Haskell/Cabal/Tasty runner parity', $statusRollup['doesNotAssert'] ?? [], true));

            $static = DelimitedTextUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);
            $csvPacket = (new DelimitedTextReader())->readCsv("Fruit,Price\nApple,25 cents\n")->children[0]->attr('delimitedText');
            $csvEvidence = is_array($csvPacket['upstreamEvidence'] ?? null) ? $csvPacket['upstreamEvidence'] : [];
            $csv = DelimitedTextUpstreamReaderEvidence::generatedCsvNativeParityEvidence($repoRoot);
            $tsv = DelimitedTextUpstreamReaderEvidence::generatedTsvNativeParityEvidence($repoRoot);
            $currentCsv = DelimitedTextUpstreamReaderEvidence::currentCsvDirectNativeParityEvidence($repoRoot);
            $currentTsv = DelimitedTextUpstreamReaderEvidence::currentTsvDirectNativeParityEvidence($repoRoot);
            $pandocCsv = DelimitedTextUpstreamReaderEvidence::generatedCsvPandocExecutableNativeParityEvidence($repoRoot);
            $pandocTsv = DelimitedTextUpstreamReaderEvidence::generatedTsvPandocExecutableNativeParityEvidence($repoRoot);

            $direct = $rollup['directFixtureEvidence'] ?? [];
            $denominator = $static['readerDenominator'] ?? [];
            $t->same($denominator['csvDirectFixtureCount'] ?? null, $direct['csvDirectFixtureCount'] ?? null);
            $t->same($denominator['tsvDirectFixtureCount'] ?? null, $direct['tsvDirectFixtureCount'] ?? null);
            $t->same(DelimitedTextUpstreamReaderEvidence::tsvDirectFixturePaths(), $direct['tsvDirectFixtures'] ?? null);
            $t->same($direct['tsvDirectFixtureCount'] ?? null, $statusRollup['tsvDirectFixtureCount'] ?? null);
            $t->same($denominator['currentCsvDirectNativePairCount'] ?? null, $direct['currentCsvDirectNativePairCount'] ?? null);
            $t->same($denominator['currentTsvDirectNativePairCount'] ?? null, $direct['currentTsvDirectNativePairCount'] ?? null);
            $t->same($denominator['csvAdjacentRstFixtureCount'] ?? null, $direct['csvAdjacentRstFixtureCount'] ?? null);
            $t->same($denominator['adjacentFixtureDenominatorImpact'] ?? null, $direct['csvAdjacentRstDirectDenominatorImpact'] ?? null);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence(['staticCurrentEvidence' => $static]));

            $parserOptions = $rollup['parserOptionFixtureEvidence'] ?? [];
            $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_CSV_PARSER_OPTION_FIXTURE_COUNT, $parserOptions['csvFixtureCount'] ?? null);
            $t->same(DelimitedTextUpstreamReaderEvidence::csvParserOptionFixtureNames(), $parserOptions['csvFixtures'] ?? null);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredCsvParserOptionFixtureEvidence($csvEvidence));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvParserOptionNativeParity($csv));

            $generated = $rollup['generatedNativeParity'] ?? [];
            $t->same($csv['sampleCount'] ?? null, $generated['csvSampleCount'] ?? null);
            $t->same($csv['generatedNativeMatchCount'] ?? null, $generated['csvMatchCount'] ?? null);
            $t->same($csv['generatedNativeMismatchCount'] ?? null, $generated['csvMismatchCount'] ?? null);
            $t->same($tsv['sampleCount'] ?? null, $generated['tsvSampleCount'] ?? null);
            $t->same($tsv['generatedNativeMatchCount'] ?? null, $generated['tsvMatchCount'] ?? null);
            $t->same($tsv['generatedNativeMismatchCount'] ?? null, $generated['tsvMismatchCount'] ?? null);
            $t->same(($csv['sampleCount'] ?? 0) + ($tsv['sampleCount'] ?? 0), $generated['totalSampleCount'] ?? null);
            $t->same(($csv['generatedNativeMatchCount'] ?? 0) + ($tsv['generatedNativeMatchCount'] ?? 0), $generated['totalMatchCount'] ?? null);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvNativeParity($csv));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvNativeParity($tsv));

            $currentCsvRollup = $rollup['currentCsvDirectNativeParity'] ?? [];
            $t->same($currentCsv['sampleCount'] ?? null, $currentCsvRollup['sampleCount'] ?? null);
            $t->same($currentCsv['currentCsvDirectNativeMatchCount'] ?? null, $currentCsvRollup['matchCount'] ?? null);
            $t->same($currentCsv['currentCsvDirectNativeMismatchCount'] ?? null, $currentCsvRollup['mismatchCount'] ?? null);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredCurrentCsvDirectNativeParity($currentCsv));

            $current = $rollup['currentTsvDirectNativeParity'] ?? [];
            $t->same($currentTsv['sampleCount'] ?? null, $current['sampleCount'] ?? null);
            $t->same($currentTsv['currentTsvDirectNativeMatchCount'] ?? null, $current['matchCount'] ?? null);
            $t->same($currentTsv['currentTsvDirectNativeMismatchCount'] ?? null, $current['mismatchCount'] ?? null);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredCurrentTsvDirectNativeParity($currentTsv));

            $executable = $rollup['pandocExecutableNativeParity'] ?? [];
            $t->same(DelimitedTextUpstreamReaderEvidence::REQUIRED_PANDOC_EXECUTABLE_VERSION, $executable['requiredPandocVersion'] ?? null);
            $t->same($pandocCsv['sampleCount'] ?? null, $executable['csvSampleCount'] ?? null);
            $t->same($pandocCsv['pandocExecutableNativeMatchCount'] ?? null, $executable['csvMatchCount'] ?? null);
            $t->same($pandocCsv['pandocExecutableNativeMismatchCount'] ?? null, $executable['csvMismatchCount'] ?? null);
            $t->same($pandocTsv['sampleCount'] ?? null, $executable['tsvSampleCount'] ?? null);
            $t->same($pandocTsv['pandocExecutableNativeMatchCount'] ?? null, $executable['tsvMatchCount'] ?? null);
            $t->same($pandocTsv['pandocExecutableNativeMismatchCount'] ?? null, $executable['tsvMismatchCount'] ?? null);
            $t->same(($pandocCsv['sampleCount'] ?? 0) + ($pandocTsv['sampleCount'] ?? 0), $executable['totalSampleCount'] ?? null);
            $t->same(($pandocCsv['pandocExecutableNativeMatchCount'] ?? 0) + ($pandocTsv['pandocExecutableNativeMatchCount'] ?? 0), $executable['totalMatchCount'] ?? null);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvPandocExecutableNativeParity($pandocCsv));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvPandocExecutableNativeParity($pandocTsv));

            $runner = $rollup['runnerEvidence'] ?? [];
            $t->same('not-run', $runner['status'] ?? null);
            $t->same('planned-not-run', $runner['commandPlanStatus'] ?? null);
            $t->same('Command:/csv.md/#1', $runner['target'] ?? null);
            $t->same(true, $runner['tsvDirectFixtureAvailable'] ?? null);
            $t->same('test/command/8661.md', $runner['tsvDirectCommandFixture'] ?? null);
            $t->same('gfm', $runner['tsvDirectOutputFormat'] ?? null);
            $t->same(false, $runner['upstreamRunnerParityClaimed'] ?? null);

            foreach ([
                '--require-honest-denominators',
                '--require-parser-option-fixture-count=9',
                '--require-generated-csv-native-parity=64',
                '--require-generated-tsv-native-parity=37',
                '--require-current-csv-direct-native-parity=2',
                '--require-current-tsv-direct-native-parity=2',
                '--require-pandoc-executable-csv-native-parity=45',
                '--require-pandoc-executable-tsv-native-parity=25',
                '--require-runner-not-run',
                '--require-runner-plan',
                '--require-no-validation-issues',
            ] as $gate) {
                $t->contains($gate, $workflow);
            }
            foreach ([
                '--require-generated-csv-native-parity=64',
                '--require-parser-option-fixture-count=9',
                '--require-generated-tsv-native-parity=37',
                '--require-current-csv-direct-native-parity=2',
                '--require-current-tsv-direct-native-parity=2',
                '--require-runner-result-artifact',
                '--require-no-validation-issues',
            ] as $gate) {
                $t->contains($gate, $runnerWorkflow);
            }
        },
];
