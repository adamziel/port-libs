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
            $workflow = $readText('.github/workflows/pandoc-html-delimited.yml');
            $runnerWorkflow = $readText('.github/workflows/pandoc-reader-runners.yml');

            $rollup = $manifest['delimitedTextReaderEvidence'] ?? null;
            $t->true(is_array($rollup), 'UPSTREAM_TEST_MANIFEST.json must carry delimited text reader evidence');
            $t->same('tracked-current-delimited-text-reader-parity-rollup', $rollup['status'] ?? null);
            $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $rollup['upstreamCommit'] ?? null);
            $t->contains('not an upstream Haskell/Cabal/Tasty runner result', (string) ($rollup['claim'] ?? ''));
            $t->contains('does not claim full CSV/TSV parity', (string) ($rollup['claim'] ?? ''));

            $static = DelimitedTextUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);
            $csvPacket = (new DelimitedTextReader())->readCsv("Fruit,Price\nApple,25 cents\n")->children[0]->attr('delimitedText');
            $csvEvidence = is_array($csvPacket['upstreamEvidence'] ?? null) ? $csvPacket['upstreamEvidence'] : [];
            $csv = DelimitedTextUpstreamReaderEvidence::generatedCsvNativeParityEvidence($repoRoot);
            $tsv = DelimitedTextUpstreamReaderEvidence::generatedTsvNativeParityEvidence($repoRoot);
            $pandocCsv = DelimitedTextUpstreamReaderEvidence::generatedCsvPandocExecutableNativeParityEvidence($repoRoot);
            $pandocTsv = DelimitedTextUpstreamReaderEvidence::generatedTsvPandocExecutableNativeParityEvidence($repoRoot);

            $direct = $rollup['directFixtureEvidence'] ?? [];
            $denominator = $static['readerDenominator'] ?? [];
            $t->same($denominator['csvDirectFixtureCount'] ?? null, $direct['csvDirectFixtureCount'] ?? null);
            $t->same($denominator['tsvDirectFixtureCount'] ?? null, $direct['tsvDirectFixtureCount'] ?? null);
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
            $t->same(false, $runner['upstreamRunnerParityClaimed'] ?? null);

            foreach ([
                '--require-honest-denominators',
                '--require-parser-option-fixture-count=9',
                '--require-generated-csv-native-parity=64',
                '--require-generated-tsv-native-parity=36',
                '--require-pandoc-executable-csv-native-parity=45',
                '--require-pandoc-executable-tsv-native-parity=24',
                '--require-runner-not-run',
                '--require-runner-plan',
                '--require-no-validation-issues',
            ] as $gate) {
                $t->contains($gate, $workflow);
            }
            foreach ([
                '--require-generated-csv-native-parity=64',
                '--require-parser-option-fixture-count=9',
                '--require-generated-tsv-native-parity=36',
                '--require-runner-result-artifact',
                '--require-no-validation-issues',
            ] as $gate) {
                $t->contains($gate, $runnerWorkflow);
            }
        },
];
