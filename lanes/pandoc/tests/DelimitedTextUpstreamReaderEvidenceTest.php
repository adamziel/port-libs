<?php

declare(strict_types=1);

use PortLibs\Pandoc\DelimitedTextUpstreamReaderEvidence;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-delimited-text-evidence-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary delimited text evidence directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary delimited text evidence directory {$base}");
    }

    return $base;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (scandir($path) ?: [] as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        $child = $path . DIRECTORY_SEPARATOR . $entry;
        if (is_dir($child)) {
            $removeTree($child);
        } else {
            @unlink($child);
        }
    }
    @rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $contents): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException("Unable to create fixture directory {$directory}");
    }
    file_put_contents($path, $contents);
};

$writeDelimitedTextEvidenceTree = static function (string $upstreamRoot, string $repoRoot) use ($writeFile): void {
    foreach ([
        'csv.md',
        '01.csv',
    ] as $name) {
        $writeFile(
            $upstreamRoot,
            'test/command/' . $name,
            (string) file_get_contents($repoRoot . '/lanes/pandoc/fixtures/upstream-current-csv-reader/' . $name)
        );
    }
    $writeFile($upstreamRoot, 'src/Text/Pandoc/CSV.hs', "module Text.Pandoc.CSV where\n");
    $writeFile($upstreamRoot, 'src/Text/Pandoc/Readers/CSV.hs', "module Text.Pandoc.Readers.CSV where\n");
};

$writeRunnerTranscripts = static function (string $root, array $paths) use ($writeFile): array {
    $records = [];
    foreach (array_values($paths) as $index => $path) {
        $contents = 'delimited text runner transcript ' . (string) ($index + 1) . "\n" . $path . "\n";
        $writeFile($root, $path, $contents);
        $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);
        $records[] = [
            'path' => $path,
            'sha256' => hash_file('sha256', $absolutePath),
            'bytes' => filesize($absolutePath),
        ];
    }

    return $records;
};

return [
    'reports skipped delimited text reader evidence when upstream root is absent' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $report = (new DelimitedTextUpstreamReaderEvidence($repoRoot, 'missing-upstream-root-for-static-gate'))->report();
        $text = DelimitedTextUpstreamReaderEvidence::formatTextReport($report);

        $t->same(1, $report['schemaVersion']);
        $t->same(DelimitedTextUpstreamReaderEvidence::TOOL_NAME, $report['tool']);
        $t->same(DelimitedTextUpstreamReaderEvidence::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
        $t->same('not-evaluated-missing-upstream-root', $report['validation']['status']);
        $t->same(['missing-upstream-root'], $report['validation']['issues']);
        $t->same('valid-checked-in-current-delimited-text-reader-evidence', $report['staticCurrentEvidence']['validation']['status']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
        $t->same(false, DelimitedTextUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlanStatus']);
        $t->same('upstream-runner-command-plan', $report['runnerEvidence']['commandPlan']['kind']);
        $t->same('planned-not-run', $report['runnerEvidence']['commandPlan']['status']);
        $t->same('hydrated Pandoc upstream checkout root', $report['runnerEvidence']['commandPlan']['workingDirectory']);
        $t->same('offline', $report['runnerEvidence']['commandPlan']['networkMode']);
        $t->same(3, $report['runnerEvidence']['commandPlan']['commandCount']);
        $t->same('upstream-runner-non-execution-boundary', $report['runnerEvidence']['executionBoundary']['kind']);
        $t->same('plan-only-not-run', $report['runnerEvidence']['executionBoundary']['status']);
        $t->same(true, $report['runnerEvidence']['executionBoundary']['planOnly']);
        $t->same(0, $report['runnerEvidence']['executionBoundary']['executedCommandCount']);
        $t->same([], $report['runnerEvidence']['executionBoundary']['executedCommands']);
        $t->same(false, $report['runnerEvidence']['executionBoundary']['upstreamRunnerParityClaimed']);
        $t->same(['Command:', 'csv.md', '#1'], $report['runnerEvidence']['target']['tastyGroupPath']);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $report['runnerEvidence']['target']['tastyPattern']);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $report['runnerEvidence']['futureCommands'][1]['arguments'][8]);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $report['runnerEvidence']['futureCommands'][2]['arguments'][7]);
        $t->same('hydrated Pandoc upstream checkout root', $report['runnerEvidence']['futureCommands'][2]['workingDirectory']);
        $t->true(in_array('.port-libs/pandoc-runner/logs/delimited-text-targeted-run.txt', $report['runnerEvidence']['requiredTranscripts'], true));
        $t->true(in_array('.port-libs/pandoc-runner/artifacts/delimited-text-targeted-run/result.json', $report['runnerEvidence']['requiredArtifacts'], true));
        $t->contains('Pandoc delimited text reader evidence', $text);
        $t->contains('Static current evidence: valid-checked-in-current-delimited-text-reader-evidence checkedInFixtures=2', $text);
        $t->contains('Generated CSV native parity: 44/44 status=generated-csv-native-parity-observed-not-upstream-fixture', $text);
        $t->contains('Generated TSV native parity: 30/30 status=generated-tsv-native-parity-observed-not-upstream-fixture', $text);
        $t->contains('Runner plan: planned-not-run', $text);
        $t->contains('Runner target: Command:/csv.md/#1', $text);
        $t->contains('Runner execution boundary: plan-only-not-run', $text);
    },
    'reports checked-in current csv command fixture static evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = DelimitedTextUpstreamReaderEvidence::checkedInCurrentEvidence($repoRoot);

        $t->same('static-checked-in-current-upstream-delimited-text-reader-fixture-evidence', $evidence['kind']);
        $t->same('4f5226df4faa0d66dd2c089465b13886360ab3c2', $evidence['upstream']['commit']);
        $t->same(2, $evidence['readerDenominator']['csvDirectFixtureCount']);
        $t->same(0, $evidence['readerDenominator']['tsvDirectFixtureCount']);
        $t->same(2, $evidence['readerDenominator']['csvAdjacentRstFixtureCount']);
        $t->same(0, $evidence['readerDenominator']['adjacentFixtureDenominatorImpact']);
        $t->same([
            'test/command/csv.md',
            'test/command/01.csv',
        ], $evidence['readerDenominator']['csvDirectFixtures']);
        $t->same([
            'test/command/3533-rst-csv-tables.csv',
            'test/command/3533-rst-csv-tables.md',
        ], $evidence['readerDenominator']['csvAdjacentRstFixtures']);
        $adjacent = $evidence['adjacentFixtureEvidence'] ?? [];
        $t->same('csv-adjacent-rst-csv-table-fixture-evidence', $adjacent['kind'] ?? null);
        $t->same('adjacent-rst-reader-fixtures-not-direct-delimited-text', $adjacent['relationship'] ?? null);
        $t->same('rst', $adjacent['reader'] ?? null);
        $t->same('csv-table', $adjacent['directive'] ?? null);
        $t->same(2, $adjacent['fixtureCount'] ?? null);
        $t->same(0, $adjacent['csvDirectFixtureDenominatorImpact'] ?? null);
        $t->same(0, $adjacent['tsvDirectFixtureDenominatorImpact'] ?? null);
        $t->same([false, false], array_column($adjacent['fixtures'] ?? [], 'directDelimitedTextReaderFixture'));
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredCsvAdjacentRstFixtureEvidence($adjacent));
        $t->same(2, $evidence['checkedInFixtureCount']);
        $t->same('csv.md', $evidence['checkedInFixtures'][0]['name']);
        $t->same('42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6', $evidence['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(2719, $evidence['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->same('01.csv', $evidence['checkedInFixtures'][1]['name']);
        $t->same('257c619e19786fddf7685a31a45f6495446a5213083540d09ecba6ce7f1e62cd', $evidence['checkedInFixtures'][1]['checkedInFile']['sha256']);
        $t->same(47, $evidence['checkedInFixtures'][1]['checkedInFile']['bytes']);
        $t->same('static-checked-in-generated-csv-native-parity-fixture-evidence', $evidence['generatedCsvNativeStaticEvidence']['kind']);
        $t->same(44, $evidence['generatedCsvNativeStaticEvidence']['sampleCount']);
        $t->same(88, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtureCount']);
        $t->same(2, $evidence['generatedCsvNativeStaticEvidence']['csvDirectFixtureDenominator']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][0]['readerOptions']);
        $t->same('quoted-multiline.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][0]['name']);
        $t->same('a038fe6edd54cf98e2b3afaf14dd4e5cbdbbdb86ab2b62d9bd60cd783ce3324e', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(80, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->same('quoted-multiline.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][1]['name']);
        $t->same('b0b4ae0c2f04421f042eef43c3a79ab699e771a3873e28b23e85d15091f03d57', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][1]['checkedInFile']['sha256']);
        $t->same(1894, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][1]['checkedInFile']['bytes']);
        $t->same('post-delimiter-space.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][2]['name']);
        $t->same('109867931d7a1d37a49d565c175d085415b378800e2acd2d4ec8f1c24935601f', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][2]['checkedInFile']['sha256']);
        $t->same(131, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][2]['checkedInFile']['bytes']);
        $t->same('post-delimiter-space.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][3]['name']);
        $t->same('766278b6bf6c85a71a50a50df5c8ee776c7e774020897f8f39e34d9841a9c8d1', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][3]['checkedInFile']['sha256']);
        $t->same(1684, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][3]['checkedInFile']['bytes']);
        $t->same('backslash-escaped-quote.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][4]['name']);
        $t->same('ae11512ae25941072ef5c297914c544a0815f2a2aba9527a9c80ca1ac5aa406e', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][4]['checkedInFile']['sha256']);
        $t->same(33, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][4]['checkedInFile']['bytes']);
        $t->same('backslash-escaped-quote.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][5]['name']);
        $t->same('0a512d33990f2629025b2eaae15e34d070fe5e985926e6d2d06d2937ac8ef1b5', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][5]['checkedInFile']['sha256']);
        $t->same(932, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][5]['checkedInFile']['bytes']);
        $t->same('backslash-escaped-quote', $evidence['generatedCsvNativeStaticEvidence']['samples'][2]['name']);
        $t->same(['escape' => '\\'], $evidence['generatedCsvNativeStaticEvidence']['samples'][2]['readerOptions']);
        $t->same('quoted-linebreak.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][6]['name']);
        $t->same('b017e1cc1434c3422538e1b16fb240ae2c35b0bda12041f568cf5da7921b0476', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][6]['checkedInFile']['sha256']);
        $t->same(48, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][6]['checkedInFile']['bytes']);
        $t->same('quoted-linebreak.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][7]['name']);
        $t->same('84472dfb9a0d40daf8c8c38cd50892cd2e13e8118e133ebfcac3720a16ae54f8', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][7]['checkedInFile']['sha256']);
        $t->same(2136, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][7]['checkedInFile']['bytes']);
        $t->same('quoted-linebreak', $evidence['generatedCsvNativeStaticEvidence']['samples'][3]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][3]['readerOptions']);
        $t->same('no-header-ragged.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][8]['name']);
        $t->same('178c37d0389b55262ee5a906f2d6a83f914da8bfd819fd37718206065baf876d', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][8]['checkedInFile']['sha256']);
        $t->same(57, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][8]['checkedInFile']['bytes']);
        $t->same('no-header-ragged.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][9]['name']);
        $t->same('2e6f817cfdf74fb6876cc386ea863d0b5469e2f5c72da6aac8c521fc9fabc8d0', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][9]['checkedInFile']['sha256']);
        $t->same(1480, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][9]['checkedInFile']['bytes']);
        $t->same('no-header-ragged', $evidence['generatedCsvNativeStaticEvidence']['samples'][4]['name']);
        $t->same(['header' => false], $evidence['generatedCsvNativeStaticEvidence']['samples'][4]['readerOptions']);
        $t->same('bom-leading-whitespace.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][10]['name']);
        $t->same('6812293a42d8d68da5c184020b3a3a4a579b6f77125080bf40486b8e433f3aec', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][10]['checkedInFile']['sha256']);
        $t->same(50, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][10]['checkedInFile']['bytes']);
        $t->same('bom-leading-whitespace.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][11]['name']);
        $t->same('9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][11]['checkedInFile']['sha256']);
        $t->same(1229, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][11]['checkedInFile']['bytes']);
        $t->same('bom-leading-whitespace', $evidence['generatedCsvNativeStaticEvidence']['samples'][5]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][5]['readerOptions']);
        $t->same('text-after-closing-quote.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][12]['name']);
        $t->same('baa94e35273deb1680660c255569262f9258132d2f97c7550b082f9676e991a6', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][12]['checkedInFile']['sha256']);
        $t->same(65, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][12]['checkedInFile']['bytes']);
        $t->same('text-after-closing-quote.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][13]['name']);
        $t->same('8e33c870e16bb77dc144c177673e3313dce9415c80bda3c9b13123466d42442e', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][13]['checkedInFile']['sha256']);
        $t->same(1246, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][13]['checkedInFile']['bytes']);
        $t->same('text-after-closing-quote', $evidence['generatedCsvNativeStaticEvidence']['samples'][6]['name']);
        $t->same(['strictParsing' => false], $evidence['generatedCsvNativeStaticEvidence']['samples'][6]['readerOptions']);
        $t->same('trailing-empty-fields.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][14]['name']);
        $t->same('2f8e15547906de3b9b95a5d354e039809382171b9d64366d751d8e493b5553d5', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][14]['checkedInFile']['sha256']);
        $t->same(62, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][14]['checkedInFile']['bytes']);
        $t->same('trailing-empty-fields.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][15]['name']);
        $t->same('86ca6197ec2c3178474e08e68f8deac8996f0fc7f994a803ec1a399e56f9f849', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][15]['checkedInFile']['sha256']);
        $t->same(1477, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][15]['checkedInFile']['bytes']);
        $t->same('trailing-empty-fields', $evidence['generatedCsvNativeStaticEvidence']['samples'][7]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][7]['readerOptions']);
        $t->same('crlf-rows.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][16]['name']);
        $t->same('9936f7d7046f8e486617541749ff65707d43e463b88577ee8c187615f7c7bc9d', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][16]['checkedInFile']['sha256']);
        $t->same(45, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][16]['checkedInFile']['bytes']);
        $t->same('crlf-rows.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][17]['name']);
        $t->same('95a70343048b4accc704b7ba0613fce1dfea60c0f719eadadb9c2c73761f2c76', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][17]['checkedInFile']['sha256']);
        $t->same(1210, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][17]['checkedInFile']['bytes']);
        $t->same('crlf-rows', $evidence['generatedCsvNativeStaticEvidence']['samples'][8]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][8]['readerOptions']);
        $t->same('unquoted-space-empty-quoted.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][18]['name']);
        $t->same('f59f8d34be7b452806cfd54e49584047e6156c6791b7df067d7452ba697ddba7', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][18]['checkedInFile']['sha256']);
        $t->same(74, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][18]['checkedInFile']['bytes']);
        $t->same('unquoted-space-empty-quoted.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][19]['name']);
        $t->same('2460dd7891857c3927c5f229fbd819afe432604a92606a61f3cb5b87d6bcd3d7', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][19]['checkedInFile']['sha256']);
        $t->same(1523, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][19]['checkedInFile']['bytes']);
        $t->same('unquoted-space-empty-quoted', $evidence['generatedCsvNativeStaticEvidence']['samples'][9]['name']);
        $t->same(['strictParsing' => false], $evidence['generatedCsvNativeStaticEvidence']['samples'][9]['readerOptions']);
        $t->same('comment-looking-data.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][20]['name']);
        $t->same('cbfda6df02a13b5ba96fcd6ab171b5083c20ef97af65e858ae110032eb9f51c8', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][20]['checkedInFile']['sha256']);
        $t->same(96, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][20]['checkedInFile']['bytes']);
        $t->same('comment-looking-data.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][21]['name']);
        $t->same('dcb0f03da9d7ec90de5ce244b3e3002b4f41cc18a9f10314189bcb457823bab6', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][21]['checkedInFile']['sha256']);
        $t->same(1617, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][21]['checkedInFile']['bytes']);
        $t->same('comment-looking-data', $evidence['generatedCsvNativeStaticEvidence']['samples'][10]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][10]['readerOptions']);
        $t->same('no-header-edge-delimiters.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][22]['name']);
        $t->same('fecf7f0f3ba6bd37411f4c8ebcd36ffedf3a9c8f1e52213fdd044ae4decc0fb1', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][22]['checkedInFile']['sha256']);
        $t->same(49, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][22]['checkedInFile']['bytes']);
        $t->same('no-header-edge-delimiters.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][23]['name']);
        $t->same('43066e049b19a9f9f6a210b3e25981d07a01915ba784dd86d8427fbf109408c9', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][23]['checkedInFile']['sha256']);
        $t->same(1395, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][23]['checkedInFile']['bytes']);
        $t->same('no-header-edge-delimiters', $evidence['generatedCsvNativeStaticEvidence']['samples'][11]['name']);
        $t->same(['header' => false], $evidence['generatedCsvNativeStaticEvidence']['samples'][11]['readerOptions']);
        $t->same('single-quote-dialect.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][24]['name']);
        $t->same('d59a5e83a298313470b808ba0381a51e3eacb0d50f317719717999e3009c1c2d', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][24]['checkedInFile']['sha256']);
        $t->same(104, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][24]['checkedInFile']['bytes']);
        $t->same('single-quote-dialect.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][25]['name']);
        $t->same('9c05ec1d28eeda63e95a2f99d84cd0ce4bd6413c6b786efb5c973f86dcdb79b6', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][25]['checkedInFile']['sha256']);
        $t->same(1646, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][25]['checkedInFile']['bytes']);
        $t->same('single-quote-dialect', $evidence['generatedCsvNativeStaticEvidence']['samples'][12]['name']);
        $t->same(['quote' => '\''], $evidence['generatedCsvNativeStaticEvidence']['samples'][12]['readerOptions']);
        $t->same('semicolon-delimiter-multiline-cell.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][26]['name']);
        $t->same('c383ab2b385dcae671a50b2b226051d74d738aaa627dd9c4393af0d39b863336', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][26]['checkedInFile']['sha256']);
        $t->same(112, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][26]['checkedInFile']['bytes']);
        $t->same('semicolon-delimiter-multiline-cell.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][27]['name']);
        $t->same('32ddacd1d7a77be7516423cc0d67ade520cf024bac92b03607dda08267dfad2f', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][27]['checkedInFile']['sha256']);
        $t->same(2016, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][27]['checkedInFile']['bytes']);
        $t->same('semicolon-delimiter-multiline-cell', $evidence['generatedCsvNativeStaticEvidence']['samples'][13]['name']);
        $t->same(['delimiter' => 'semicolon'], $evidence['generatedCsvNativeStaticEvidence']['samples'][13]['readerOptions']);
        $t->same('cr-only-rows.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][28]['name']);
        $t->same('fca94752c9fdfbe612a0a998c33a2ba3d5fd816db58ab9648bd41d9318bf3624', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][28]['checkedInFile']['sha256']);
        $t->same(45, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][28]['checkedInFile']['bytes']);
        $t->same('cr-only-rows.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][29]['name']);
        $t->same('e3bad4c4dc164b635eec375b48010d2b7cecd6e94274b5cc90484e24276f6a91', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][29]['checkedInFile']['sha256']);
        $t->same(1145, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][29]['checkedInFile']['bytes']);
        $t->same('cr-only-rows', $evidence['generatedCsvNativeStaticEvidence']['samples'][14]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][14]['readerOptions']);
        $t->same('unterminated-quote-eof.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][30]['name']);
        $t->same('272c4e0c03e402d21e2b808459fc913dd3eacc2e7c9dafdfb6f506c8127eb747', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][30]['checkedInFile']['sha256']);
        $t->same(34, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][30]['checkedInFile']['bytes']);
        $t->same('unterminated-quote-eof.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][31]['name']);
        $t->same('754ba8a6135cf7f7064b714cb6a33990958865e0a5ee04532710a74cc395e74b', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][31]['checkedInFile']['sha256']);
        $t->same(925, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][31]['checkedInFile']['bytes']);
        $t->same('unterminated-quote-eof', $evidence['generatedCsvNativeStaticEvidence']['samples'][15]['name']);
        $t->same(['strictParsing' => false], $evidence['generatedCsvNativeStaticEvidence']['samples'][15]['readerOptions']);
        $t->same('duplicate-header-labels.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][32]['name']);
        $t->same('d0627dffb43d149d884fba447424eed9544c36f9885516afd3e2a04e807c101f', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][32]['checkedInFile']['sha256']);
        $t->same(42, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][32]['checkedInFile']['bytes']);
        $t->same('duplicate-header-labels.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][33]['name']);
        $t->same('7e2b213a1c5fa209f5c3f41187012455d9bd701b2da6ff379b15519707ff938e', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][33]['checkedInFile']['sha256']);
        $t->same(1211, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][33]['checkedInFile']['bytes']);
        $t->same('duplicate-header-labels', $evidence['generatedCsvNativeStaticEvidence']['samples'][16]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][16]['readerOptions']);
        $t->same('keep-space-after-comma.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][34]['name']);
        $t->same('68e6bdf13bdb5129562eca08ba28a7516377821d8d2cf951f2927ae923dfb656', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][34]['checkedInFile']['sha256']);
        $t->same(118, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][34]['checkedInFile']['bytes']);
        $t->same('keep-space-after-comma.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][35]['name']);
        $t->same('5a110b2e35a46a8a3e98961b0a68baf210d015a374c99fdd04c60dfee641c721', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][35]['checkedInFile']['sha256']);
        $t->same(1731, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][35]['checkedInFile']['bytes']);
        $t->same('keep-space-after-comma', $evidence['generatedCsvNativeStaticEvidence']['samples'][17]['name']);
        $t->same(['keepSpace' => true], $evidence['generatedCsvNativeStaticEvidence']['samples'][17]['readerOptions']);
        $t->same('space-delimiter-single-quote.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][36]['name']);
        $t->same('577165de4a8e2beaee7ef748dc7686c9a283f71e730f8d2e21be94e16cde65f4', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][36]['checkedInFile']['sha256']);
        $t->same(79, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][36]['checkedInFile']['bytes']);
        $t->same('space-delimiter-single-quote.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][37]['name']);
        $t->same('594390fc80d43bada7903e66a771be44bbef23b24a7f11a2e9ac87e96bc542dd', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][37]['checkedInFile']['sha256']);
        $t->same(1579, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][37]['checkedInFile']['bytes']);
        $t->same('space-delimiter-single-quote', $evidence['generatedCsvNativeStaticEvidence']['samples'][18]['name']);
        $t->same(['delimiter' => 'space', 'quote' => '\''], $evidence['generatedCsvNativeStaticEvidence']['samples'][18]['readerOptions']);
        $t->same('blank-row-skipped.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][38]['name']);
        $t->same('4d721ac02e32060a616d3fef61083cc6f88adae5ace5ced3d77fe5f6fb966321', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][38]['checkedInFile']['sha256']);
        $t->same(71, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][38]['checkedInFile']['bytes']);
        $t->same('blank-row-skipped.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][39]['name']);
        $t->same('cf931bb22f5eeb8934579b99d4109e60801dd40e9f48e4e78a4e24038bc07a5f', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][39]['checkedInFile']['sha256']);
        $t->same(1555, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][39]['checkedInFile']['bytes']);
        $t->same('blank-row-skipped', $evidence['generatedCsvNativeStaticEvidence']['samples'][19]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][19]['readerOptions']);
        $t->same('backslash-escaped-nonquote.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][40]['name']);
        $t->same('e93eadf2bb257f0e678680ac6e9e2c5b6895410c70e91b414e727da53b8cbd43', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][40]['checkedInFile']['sha256']);
        $t->same(85, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][40]['checkedInFile']['bytes']);
        $t->same('backslash-escaped-nonquote.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][41]['name']);
        $t->same('155fe9867cd9cca831158d85716c5ef1368c60fddd8edad116b8e067ab465eb9', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][41]['checkedInFile']['sha256']);
        $t->same(1601, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][41]['checkedInFile']['bytes']);
        $t->same('backslash-escaped-nonquote', $evidence['generatedCsvNativeStaticEvidence']['samples'][20]['name']);
        $t->same(['escape' => '\\'], $evidence['generatedCsvNativeStaticEvidence']['samples'][20]['readerOptions']);
        $t->same('pipe-delimiter-quoted-field.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][42]['name']);
        $t->same('260877bbb70ff332d8bcff85e829231f71de1dc6d3584fca014e1b3861aab6f8', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][42]['checkedInFile']['sha256']);
        $t->same(118, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][42]['checkedInFile']['bytes']);
        $t->same('pipe-delimiter-quoted-field.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][43]['name']);
        $t->same('2df2bf05bc29b8b1484e85435e332eff22e71e81aab2c46c2ce3c8caf75d939b', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][43]['checkedInFile']['sha256']);
        $t->same(1697, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][43]['checkedInFile']['bytes']);
        $t->same('pipe-delimiter-quoted-field', $evidence['generatedCsvNativeStaticEvidence']['samples'][21]['name']);
        $t->same(['delimiter' => 'pipe'], $evidence['generatedCsvNativeStaticEvidence']['samples'][21]['readerOptions']);
        $t->same('quote-disabled-literal.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][44]['name']);
        $t->same('d660c2016f15d2181c677dd6545d768f579d6cffcaed5909292260420cf8efde', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][44]['checkedInFile']['sha256']);
        $t->same(96, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][44]['checkedInFile']['bytes']);
        $t->same('quote-disabled-literal.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][45]['name']);
        $t->same('0f5b9311d4ace127a447f0ab12474ca032d67db6ea57300ed95cd995d4ff8d5e', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][45]['checkedInFile']['sha256']);
        $t->same(1606, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][45]['checkedInFile']['bytes']);
        $t->same('quote-disabled-literal', $evidence['generatedCsvNativeStaticEvidence']['samples'][22]['name']);
        $t->same(['quote' => false], $evidence['generatedCsvNativeStaticEvidence']['samples'][22]['readerOptions']);
        $t->same('blank-input.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][46]['name']);
        $t->same('01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][46]['checkedInFile']['sha256']);
        $t->same(1, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][46]['checkedInFile']['bytes']);
        $t->same('blank-input.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][47]['name']);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][47]['checkedInFile']['sha256']);
        $t->same(3, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][47]['checkedInFile']['bytes']);
        $t->same('blank-input', $evidence['generatedCsvNativeStaticEvidence']['samples'][23]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][23]['readerOptions']);
        $t->same('unicode-safe.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][48]['name']);
        $t->same('fc76c7b95aec02b9c85b4f435682cab9b5003be0a0f698117ec062e80ea59929', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][48]['checkedInFile']['sha256']);
        $t->same(91, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][48]['checkedInFile']['bytes']);
        $t->same('unicode-safe.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][49]['name']);
        $t->same('d4e72fa00d0fcb0f7b1ea4bd44561f5aaadb710f0420b5bc7f78cf0c72a277fe', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][49]['checkedInFile']['sha256']);
        $t->same(1364, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][49]['checkedInFile']['bytes']);
        $t->same('unicode-safe', $evidence['generatedCsvNativeStaticEvidence']['samples'][24]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][24]['readerOptions']);
        $t->same('quote-in-unquoted-field.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][50]['name']);
        $t->same('83cdb32eeb44e162f294a30313f3652df81a16df4a298969cb80ecef0277f8d4', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][50]['checkedInFile']['sha256']);
        $t->same(47, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][50]['checkedInFile']['bytes']);
        $t->same('quote-in-unquoted-field.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][51]['name']);
        $t->same('bf2d71e0867ca7b1487c59cff7bf7912d03783dc646003bc3eb0f7a44a3eb9f1', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][51]['checkedInFile']['sha256']);
        $t->same(1217, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][51]['checkedInFile']['bytes']);
        $t->same('quote-in-unquoted-field', $evidence['generatedCsvNativeStaticEvidence']['samples'][25]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][25]['readerOptions']);
        $t->same('header-only.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][52]['name']);
        $t->same('8d10b9e38497ef13bc091e1574b71423a614593e489bd5af9943f946a0296dad', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][52]['checkedInFile']['sha256']);
        $t->same(18, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][52]['checkedInFile']['bytes']);
        $t->same('header-only.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][53]['name']);
        $t->same('6c1d2eed4478d45205fe2f2fb63b3ba282aad8c27f37b5a01168ba689bee0f00', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][53]['checkedInFile']['sha256']);
        $t->same(610, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][53]['checkedInFile']['bytes']);
        $t->same('header-only', $evidence['generatedCsvNativeStaticEvidence']['samples'][26]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][26]['readerOptions']);
        $t->same('leading-whitespace-record.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][54]['name']);
        $t->same('f3365cd5dd45cc2aee1135d4c538390734856d50e5fccf2417b7b8a0568dde89', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][54]['checkedInFile']['sha256']);
        $t->same(10, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][54]['checkedInFile']['bytes']);
        $t->same('leading-whitespace-record.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][55]['name']);
        $t->same('a18a1b109ba5943baea04ee1f42bbae8e5d4121d3250745ea61e6178f0324846', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][55]['checkedInFile']['sha256']);
        $t->same(577, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][55]['checkedInFile']['bytes']);
        $t->same('leading-whitespace-record', $evidence['generatedCsvNativeStaticEvidence']['samples'][27]['name']);
        $t->same(['strictParsing' => true], $evidence['generatedCsvNativeStaticEvidence']['samples'][27]['readerOptions']);
        $t->same('leading-blank-whitespace.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][56]['name']);
        $t->same('009966d20c582967816f9721a10b558b07333c88849bff11176b5140e746191e', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][56]['checkedInFile']['sha256']);
        $t->same(3, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][56]['checkedInFile']['bytes']);
        $t->same('leading-blank-whitespace.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][57]['name']);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][57]['checkedInFile']['sha256']);
        $t->same(3, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][57]['checkedInFile']['bytes']);
        $t->same('leading-blank-whitespace', $evidence['generatedCsvNativeStaticEvidence']['samples'][28]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][28]['readerOptions']);
        $t->same('quoted-final-vtab-whitespace.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][58]['name']);
        $t->same('295f211324039598c36a9f427e0c9075833fe2b835459f0a65b62936dfcdaaa4', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][58]['checkedInFile']['sha256']);
        $t->same(16, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][58]['checkedInFile']['bytes']);
        $t->same('quoted-final-vtab-whitespace.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][59]['name']);
        $t->same('8f33457b985b91dafdf573244515410fb7d5d43a1004a86ad42845a323c55aff', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][59]['checkedInFile']['sha256']);
        $t->same(680, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][59]['checkedInFile']['bytes']);
        $t->same('quoted-final-vtab-whitespace', $evidence['generatedCsvNativeStaticEvidence']['samples'][29]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][29]['readerOptions']);
        $t->same('unquoted-final-formfeed.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][60]['name']);
        $t->same('80650824fca0b4705c51a54aa7328f4ed13db4a51c55ac3603e7fa55ce295beb', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][60]['checkedInFile']['sha256']);
        $t->same(12, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][60]['checkedInFile']['bytes']);
        $t->same('unquoted-final-formfeed.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][61]['name']);
        $t->same('a3fbb8cf65627ffdb520bb05437dd79096ccf633cffc8d6537b920738e1db792', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][61]['checkedInFile']['sha256']);
        $t->same(683, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][61]['checkedInFile']['bytes']);
        $t->same('unquoted-final-formfeed', $evidence['generatedCsvNativeStaticEvidence']['samples'][30]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][30]['readerOptions']);
        $t->same('space-only-record.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][62]['name']);
        $t->same('e16f1596201850fd4a63680b27f603cb64e67176159be3d8ed78a4403fdb1700', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][62]['checkedInFile']['sha256']);
        $t->same(2, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][62]['checkedInFile']['bytes']);
        $t->same('space-only-record.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][63]['name']);
        $t->same('2643d68a231e44eded9e7ea0647254e3435024cf2dba73ffe924bcdffcab2ae3', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][63]['checkedInFile']['sha256']);
        $t->same(344, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][63]['checkedInFile']['bytes']);
        $t->same('space-only-record', $evidence['generatedCsvNativeStaticEvidence']['samples'][31]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][31]['readerOptions']);
        $t->same('quoted-trailing-linebreak.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][64]['name']);
        $t->same('c806b117273e5a54d3c91a0ada3051854672d049e6a9b62362c0665b5969a56b', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][64]['checkedInFile']['sha256']);
        $t->same(55, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][64]['checkedInFile']['bytes']);
        $t->same('quoted-trailing-linebreak.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][65]['name']);
        $t->same('124132d6e2d241254ed916f8a2d21439fc3e16acc306cee80c98b2fa43f7c2bb', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][65]['checkedInFile']['sha256']);
        $t->same(2363, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][65]['checkedInFile']['bytes']);
        $t->same('quoted-trailing-linebreak', $evidence['generatedCsvNativeStaticEvidence']['samples'][32]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][32]['readerOptions']);
        $t->same('leading-empty-fields.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][66]['name']);
        $t->same('ae9dd96c1d786a3a17e28f8b12a8209d8ccce05339b099ab2ea9ffbc8024e82a', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][66]['checkedInFile']['sha256']);
        $t->same(36, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][66]['checkedInFile']['bytes']);
        $t->same('leading-empty-fields.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][67]['name']);
        $t->same('7b88f2fd371de6bda7ed4d4c0de1bfbb9eb7726231cdf6551918735c60c33bf1', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][67]['checkedInFile']['sha256']);
        $t->same(1866, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][67]['checkedInFile']['bytes']);
        $t->same('leading-empty-fields', $evidence['generatedCsvNativeStaticEvidence']['samples'][33]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][33]['readerOptions']);
        $t->same('quoted-header-fields.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][68]['name']);
        $t->same('20a690bd9ec550c7bef7124c2be17ec2adcdfde55e227f174527154fa2fb005e', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][68]['checkedInFile']['sha256']);
        $t->same(63, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][68]['checkedInFile']['bytes']);
        $t->same('quoted-header-fields.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][69]['name']);
        $t->same('7dc4b37ae154cc6c61fe2044fd857198c7cddeedb11dd703760bfc056ab74525', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][69]['checkedInFile']['sha256']);
        $t->same(1260, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][69]['checkedInFile']['bytes']);
        $t->same('quoted-header-fields', $evidence['generatedCsvNativeStaticEvidence']['samples'][34]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][34]['readerOptions']);
        $t->same('unquoted-tab-cell.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][70]['name']);
        $t->same('05913c8dfbf085e9a4bce6e7cb78a0cf21bcc730c8c9e99a46ef568095febaea', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][70]['checkedInFile']['sha256']);
        $t->same(22, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][70]['checkedInFile']['bytes']);
        $t->same('unquoted-tab-cell.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][71]['name']);
        $t->same('af548f35ca48115c87452df2017c558d88a0b9ffae7923419d3572d8894c9099', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][71]['checkedInFile']['sha256']);
        $t->same(1153, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][71]['checkedInFile']['bytes']);
        $t->same('unquoted-tab-cell', $evidence['generatedCsvNativeStaticEvidence']['samples'][35]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][35]['readerOptions']);
        $t->same('quoted-empty-fields.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][72]['name']);
        $t->same('e1f1ebedf2b64fc3de8e758427aed94132e6ac506d235fe6962543c8b6e12a30', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][72]['checkedInFile']['sha256']);
        $t->same(51, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][72]['checkedInFile']['bytes']);
        $t->same('quoted-empty-fields.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][73]['name']);
        $t->same('b54f94d0f28e8d0591abad246ebcf26ea9f486560bd98c5ccb5bb16385c2b21f', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][73]['checkedInFile']['sha256']);
        $t->same(2439, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][73]['checkedInFile']['bytes']);
        $t->same('quoted-empty-fields', $evidence['generatedCsvNativeStaticEvidence']['samples'][36]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][36]['readerOptions']);
        $t->same('leading-whitespace-before-quote.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][74]['name']);
        $t->same('de435cced6dfe6d32ffb53fb28ed4f9c2202b6c0900c82ddc5edd351576c03a3', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][74]['checkedInFile']['sha256']);
        $t->same(86, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][74]['checkedInFile']['bytes']);
        $t->same('leading-whitespace-before-quote.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][75]['name']);
        $t->same('0ed2ba01e1eaca6d5b82d7b75f1b13087f27c4fe5ff2ab9d1e137c3d284e01e5', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][75]['checkedInFile']['sha256']);
        $t->same(2371, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][75]['checkedInFile']['bytes']);
        $t->same('leading-whitespace-before-quote', $evidence['generatedCsvNativeStaticEvidence']['samples'][37]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][37]['readerOptions']);
        $t->same('post-delimiter-tab.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][76]['name']);
        $t->same('330ad95f24c4f732fb00c829df8ba209be3972e1c424a4734c60d34e535fbc43', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][76]['checkedInFile']['sha256']);
        $t->same(80, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][76]['checkedInFile']['bytes']);
        $t->same('post-delimiter-tab.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][77]['name']);
        $t->same('c9c3e78d71ded4a8b14f2fe66631b11f58a914683a18ef59dc51f99f3c4e7215', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][77]['checkedInFile']['sha256']);
        $t->same(1319, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][77]['checkedInFile']['bytes']);
        $t->same('post-delimiter-tab', $evidence['generatedCsvNativeStaticEvidence']['samples'][38]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][38]['readerOptions']);
        $t->same('quoted-blank-line-cell.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][78]['name']);
        $t->same('125c8c5b1b014ada9f163e5c1e0d90abb4392728cb01e6950c312b362e9d90eb', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][78]['checkedInFile']['sha256']);
        $t->same(34, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][78]['checkedInFile']['bytes']);
        $t->same('quoted-blank-line-cell.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][79]['name']);
        $t->same('c246fe7f10c8983792119be048df8360dd3382315dd136775370944206725ef9', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][79]['checkedInFile']['sha256']);
        $t->same(1595, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][79]['checkedInFile']['bytes']);
        $t->same('quoted-blank-line-cell', $evidence['generatedCsvNativeStaticEvidence']['samples'][39]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][39]['readerOptions']);
        $t->same('blank-leading-header.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][80]['name']);
        $t->same('e3578702642814615d38649dc6885e5862cb727ea9e418b076d8a9d2cc592525', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][80]['checkedInFile']['sha256']);
        $t->same(38, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][80]['checkedInFile']['bytes']);
        $t->same('blank-leading-header.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][81]['name']);
        $t->same('32cb6fb96be43fdaabf0372ffa28ca17fad44ee8789d764fed8fd86df97ad847', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][81]['checkedInFile']['sha256']);
        $t->same(2032, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][81]['checkedInFile']['bytes']);
        $t->same('blank-leading-header', $evidence['generatedCsvNativeStaticEvidence']['samples'][40]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][40]['readerOptions']);
        $t->same('pre-delimiter-space.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][82]['name']);
        $t->same('cf31b6c9a903bdc7743edd7be575dc1da5987b6dbd18525f8bf5c88d7cb61d0b', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][82]['checkedInFile']['sha256']);
        $t->same(52, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][82]['checkedInFile']['bytes']);
        $t->same('pre-delimiter-space.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][83]['name']);
        $t->same('aecb3a09cd5c535db2000bca63f7c477e3dd8d3757631268783730ab2ebad7a8', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][83]['checkedInFile']['sha256']);
        $t->same(2179, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][83]['checkedInFile']['bytes']);
        $t->same('pre-delimiter-space', $evidence['generatedCsvNativeStaticEvidence']['samples'][41]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][41]['readerOptions']);
        $t->same('markdown-syntax-literal.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][84]['name']);
        $t->same('54efc59a5af4fdbb175321dedea7de7df6e5c1257dc9d58201a4a9eebe8a06ed', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][84]['checkedInFile']['sha256']);
        $t->same(77, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][84]['checkedInFile']['bytes']);
        $t->same('markdown-syntax-literal.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][85]['name']);
        $t->same('b53dd045777e3aeb09537e85c060f30529302052616c31565652ecf15c66f773', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][85]['checkedInFile']['sha256']);
        $t->same(2317, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][85]['checkedInFile']['bytes']);
        $t->same('markdown-syntax-literal', $evidence['generatedCsvNativeStaticEvidence']['samples'][42]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][42]['readerOptions']);
        $t->same('formula-looking-literals.csv', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][86]['name']);
        $t->same('25b3fd258d1d8eac491f5c337c1e9dd68020586339d0a7254df05c74f0b21f12', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][86]['checkedInFile']['sha256']);
        $t->same(89, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][86]['checkedInFile']['bytes']);
        $t->same('formula-looking-literals.native', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][87]['name']);
        $t->same('cd5252c5c890122ababd2e5566f4ae9a02fcf1d23d6c718c6bf77cb72e42ffa5', $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][87]['checkedInFile']['sha256']);
        $t->same(2185, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtures'][87]['checkedInFile']['bytes']);
        $t->same('formula-looking-literals', $evidence['generatedCsvNativeStaticEvidence']['samples'][43]['name']);
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][43]['readerOptions']);
        $t->same('static-checked-in-generated-tsv-native-parity-fixture-evidence', $evidence['generatedTsvNativeStaticEvidence']['kind']);
        $t->same(30, $evidence['generatedTsvNativeStaticEvidence']['sampleCount']);
        $t->same(60, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtureCount']);
        $t->same(0, $evidence['generatedTsvNativeStaticEvidence']['tsvDirectFixtureDenominator']);
        $t->same('simple.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][0]['name']);
        $t->same('fcee0aed5a2fde11bbd19f2fc4445357a0d7bbd9c9962df6630fed4b6178ff8e', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][0]['checkedInFile']['sha256']);
        $t->same(71, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][0]['checkedInFile']['bytes']);
        $t->same('simple.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][1]['name']);
        $t->same('f4c930c9d309c4dd6ec1c50eda9e45ff3614566e6c26e4b5254ce3e9c62abb2a', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][1]['checkedInFile']['sha256']);
        $t->same(1540, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][1]['checkedInFile']['bytes']);
        $t->same('quote-trailing.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][2]['name']);
        $t->same('c5694bc5e74a5920c4752369bd967be614f3d7f8fde6395bcd05c9b5f22d85dd', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][2]['checkedInFile']['sha256']);
        $t->same(102, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][2]['checkedInFile']['bytes']);
        $t->same('quote-trailing.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][3]['name']);
        $t->same('51b8ce6dc3164f654f50f7fc1597e2788b04a2b634a32a3f52d51951b68260b6', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][3]['checkedInFile']['sha256']);
        $t->same(1975, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][3]['checkedInFile']['bytes']);
        $t->same('unicode-safe.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][4]['name']);
        $t->same('cd7a0f7e2c4737a1884c0ff3ec73bf6a5990fbdfb6ba1b588b6a6d9202ab3e02', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][4]['checkedInFile']['sha256']);
        $t->same(91, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][4]['checkedInFile']['bytes']);
        $t->same('unicode-safe.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][5]['name']);
        $t->same('e7d3ea0f37e8d3b0613155eaaf480edf042cd5e22aa4291866ae8a0e627fe990', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][5]['checkedInFile']['sha256']);
        $t->same(1370, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][5]['checkedInFile']['bytes']);
        $t->same('ragged-blank-fields.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][6]['name']);
        $t->same('3eb62cad900b02542011bfcb6ffa891856dbf398aa7e7174785264494258c9d4', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][6]['checkedInFile']['sha256']);
        $t->same(76, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][6]['checkedInFile']['bytes']);
        $t->same('ragged-blank-fields.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][7]['name']);
        $t->same('3dff8bc1804021464a9c00917917904cef8c259d3933410507bb0a6961899bce', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][7]['checkedInFile']['sha256']);
        $t->same(1756, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][7]['checkedInFile']['bytes']);
        $t->same('no-header.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][8]['name']);
        $t->same('0553e41c6e8a6257ad01d8dfad5c1ffecfb495a58273b38b1115ddb5635449bd', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][8]['checkedInFile']['sha256']);
        $t->same(37, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][8]['checkedInFile']['bytes']);
        $t->same('no-header.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][9]['name']);
        $t->same('9d9356cfcfb719fb3093faf108a3f70cbf15dfb3921b37420d8d6a3eef3caf46', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][9]['checkedInFile']['sha256']);
        $t->same(1186, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][9]['checkedInFile']['bytes']);
        $t->same('no-header', $evidence['generatedTsvNativeStaticEvidence']['samples'][4]['name']);
        $t->same(['header' => false], $evidence['generatedTsvNativeStaticEvidence']['samples'][4]['readerOptions']);
        $t->same('bom-leading-whitespace.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][10]['name']);
        $t->same('d10a56e1e3d9cdf0abb8c3f800d45a8bace164a4ff015c72dad5b5206b55f451', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][10]['checkedInFile']['sha256']);
        $t->same(48, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][10]['checkedInFile']['bytes']);
        $t->same('bom-leading-whitespace.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][11]['name']);
        $t->same('9657368b59d4181c81246a5a11bd5dba277a29088dfdc392c31e2a44fd615e36', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][11]['checkedInFile']['sha256']);
        $t->same(1229, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][11]['checkedInFile']['bytes']);
        $t->same('bom-leading-whitespace', $evidence['generatedTsvNativeStaticEvidence']['samples'][5]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][5]['readerOptions']);
        $t->same('blank-row-literal-punctuation.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][12]['name']);
        $t->same('3971c352574fb88bf49073fab5e73d309c3e50d23c169250aec22e8ed3e0c4d8', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][12]['checkedInFile']['sha256']);
        $t->same(51, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][12]['checkedInFile']['bytes']);
        $t->same('blank-row-literal-punctuation.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][13]['name']);
        $t->same('29623a127b4bc0bf3f17b351bfa9f712a1ecbd2d24741d3c2f6aa0475e250023', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][13]['checkedInFile']['sha256']);
        $t->same(1253, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][13]['checkedInFile']['bytes']);
        $t->same('blank-row-literal-punctuation', $evidence['generatedTsvNativeStaticEvidence']['samples'][6]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][6]['readerOptions']);
        $t->same('comment-looking-data.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][14]['name']);
        $t->same('a52c8e6587c36a1deb6d86bce90910eb138f9ed983ba66c6336eca055f0e9d04', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][14]['checkedInFile']['sha256']);
        $t->same(84, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][14]['checkedInFile']['bytes']);
        $t->same('comment-looking-data.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][15]['name']);
        $t->same('52a97c04e576bedd6bec2609850c3a65c3a90fc165326d9ab11beae1f447cc2e', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][15]['checkedInFile']['sha256']);
        $t->same(1399, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][15]['checkedInFile']['bytes']);
        $t->same('comment-looking-data', $evidence['generatedTsvNativeStaticEvidence']['samples'][7]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][7]['readerOptions']);
        $t->same('no-header-edge-delimiters.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][16]['name']);
        $t->same('0e90d36fbdce51c4ee0557fa0d1526d849493f30d408675cc445094b7ae79e45', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][16]['checkedInFile']['sha256']);
        $t->same(58, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][16]['checkedInFile']['bytes']);
        $t->same('no-header-edge-delimiters.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][17]['name']);
        $t->same('1e219ae43ee7ef40c4b05ba0565a1e1f7b127a3b6ddda615ce5d9e87622446a4', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][17]['checkedInFile']['sha256']);
        $t->same(1769, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][17]['checkedInFile']['bytes']);
        $t->same('no-header-edge-delimiters', $evidence['generatedTsvNativeStaticEvidence']['samples'][8]['name']);
        $t->same(['header' => false], $evidence['generatedTsvNativeStaticEvidence']['samples'][8]['readerOptions']);
        $t->same('csv-quoted-literal.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][18]['name']);
        $t->same('1c28f3c034a65a005034ae5806e4d035eecd9704c6cf1055b2f0c041e96719be', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][18]['checkedInFile']['sha256']);
        $t->same(129, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][18]['checkedInFile']['bytes']);
        $t->same('csv-quoted-literal.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][19]['name']);
        $t->same('419fb3357404e8b572bf42e5fe3cc32c410f4b69566b282295a7039490ab6fdc', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][19]['checkedInFile']['sha256']);
        $t->same(1734, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][19]['checkedInFile']['bytes']);
        $t->same('csv-quoted-literal', $evidence['generatedTsvNativeStaticEvidence']['samples'][9]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][9]['readerOptions']);
        $t->same('keep-space-after-tab.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][20]['name']);
        $t->same('4a015006efd98569714058528747683dd5e3a384a0a9615d7d7ebce3bcd8e603', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][20]['checkedInFile']['sha256']);
        $t->same(119, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][20]['checkedInFile']['bytes']);
        $t->same('keep-space-after-tab.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][21]['name']);
        $t->same('88ffc2cd12c0dd74592bceeb20821ec9a38c10f87e9b60a808ca03569c9c1026', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][21]['checkedInFile']['sha256']);
        $t->same(1725, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][21]['checkedInFile']['bytes']);
        $t->same('keep-space-after-tab', $evidence['generatedTsvNativeStaticEvidence']['samples'][10]['name']);
        $t->same(['keepSpace' => true], $evidence['generatedTsvNativeStaticEvidence']['samples'][10]['readerOptions']);
        $t->same('crlf-rows.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][22]['name']);
        $t->same('1ee34fc2887a5be7359dd06425faa9e15c47cc7fd65ea5b475119cf159951eb4', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][22]['checkedInFile']['sha256']);
        $t->same(44, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][22]['checkedInFile']['bytes']);
        $t->same('crlf-rows.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][23]['name']);
        $t->same('ae90f3b65232ccb820321bacbc03f1f45224cfcfdb7eb2614315e124d91905e0', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][23]['checkedInFile']['sha256']);
        $t->same(1210, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][23]['checkedInFile']['bytes']);
        $t->same('crlf-rows', $evidence['generatedTsvNativeStaticEvidence']['samples'][11]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][11]['readerOptions']);
        $t->same('quoted-tabs-and-newlines.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][24]['name']);
        $t->same('063ef586c65fd208bfb670a711edbd004501bb484fe5facbed94c6f898bb6f79', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][24]['checkedInFile']['sha256']);
        $t->same(94, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][24]['checkedInFile']['bytes']);
        $t->same('quoted-tabs-and-newlines.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][25]['name']);
        $t->same('dbfdd6519302270f48a6831a9e0594d7779e14922b9f8fd120eee2a7204d2b5b', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][25]['checkedInFile']['sha256']);
        $t->same(1615, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][25]['checkedInFile']['bytes']);
        $t->same('quoted-tabs-and-newlines', $evidence['generatedTsvNativeStaticEvidence']['samples'][12]['name']);
        $t->same(['quote' => '"'], $evidence['generatedTsvNativeStaticEvidence']['samples'][12]['readerOptions']);
        $t->same('blank-leading-header.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][26]['name']);
        $t->same('c2fd8d6c08e7858885d36a4d57a4f79f473418772f1c9f5c6f128b6fbba9858c', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][26]['checkedInFile']['sha256']);
        $t->same(21, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][26]['checkedInFile']['bytes']);
        $t->same('blank-leading-header.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][27]['name']);
        $t->same('36321b161eb2743b361b6e5f2d8062b2de6d006969f64290fcbb84bb3d180ed2', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][27]['checkedInFile']['sha256']);
        $t->same(872, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][27]['checkedInFile']['bytes']);
        $t->same('blank-leading-header', $evidence['generatedTsvNativeStaticEvidence']['samples'][13]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][13]['readerOptions']);
        $t->same('basic-status.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][28]['name']);
        $t->same('d05b3c50b6780930533f48d3e8192cb4a50ee2f15dec69d75984d10f43dba22d', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][28]['checkedInFile']['sha256']);
        $t->same(61, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][28]['checkedInFile']['bytes']);
        $t->same('basic-status.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][29]['name']);
        $t->same('71b49eeb3ed15b82ae55464884fd30a7bf4191dbd04fb2625bea3a862896c4a9', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][29]['checkedInFile']['sha256']);
        $t->same(1262, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][29]['checkedInFile']['bytes']);
        $t->same('basic-status', $evidence['generatedTsvNativeStaticEvidence']['samples'][14]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][14]['readerOptions']);
        $t->same('header-only.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][30]['name']);
        $t->same('46486ef39ea30bfa8f03905b713e20d76b78ee760e4e586931fd5008db45abe6', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][30]['checkedInFile']['sha256']);
        $t->same(18, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][30]['checkedInFile']['bytes']);
        $t->same('header-only.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][31]['name']);
        $t->same('6c1d2eed4478d45205fe2f2fb63b3ba282aad8c27f37b5a01168ba689bee0f00', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][31]['checkedInFile']['sha256']);
        $t->same(610, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][31]['checkedInFile']['bytes']);
        $t->same('header-only', $evidence['generatedTsvNativeStaticEvidence']['samples'][15]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][15]['readerOptions']);
        $t->same('no-header-internal-trailing-empty.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][32]['name']);
        $t->same('4147bfbde51a4e832fe461334bc8657c055dca86d4b274dee8c3adab32cab9cd', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][32]['checkedInFile']['sha256']);
        $t->same(33, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][32]['checkedInFile']['bytes']);
        $t->same('no-header-internal-trailing-empty.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][33]['name']);
        $t->same('c3fade20df04245e26fd3e54990284f7e1a8750c882c2557ec520c75faab46f5', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][33]['checkedInFile']['sha256']);
        $t->same(1363, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][33]['checkedInFile']['bytes']);
        $t->same('no-header-internal-trailing-empty', $evidence['generatedTsvNativeStaticEvidence']['samples'][16]['name']);
        $t->same(['header' => false], $evidence['generatedTsvNativeStaticEvidence']['samples'][16]['readerOptions']);
        $t->same('blank-input.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][34]['name']);
        $t->same('01ba4719c80b6fe911b091a7c05124b64eeece964e09c058ef8f9805daca546b', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][34]['checkedInFile']['sha256']);
        $t->same(1, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][34]['checkedInFile']['bytes']);
        $t->same('blank-input.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][35]['name']);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][35]['checkedInFile']['sha256']);
        $t->same(3, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][35]['checkedInFile']['bytes']);
        $t->same('blank-input', $evidence['generatedTsvNativeStaticEvidence']['samples'][17]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][17]['readerOptions']);
        $t->same('duplicate-header-labels.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][36]['name']);
        $t->same('d973ebe3ce9f9aab73fecd99f1c85e901f0f572089d69deb6f7eb9dee79d0e23', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][36]['checkedInFile']['sha256']);
        $t->same(42, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][36]['checkedInFile']['bytes']);
        $t->same('duplicate-header-labels.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][37]['name']);
        $t->same('7e2b213a1c5fa209f5c3f41187012455d9bd701b2da6ff379b15519707ff938e', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][37]['checkedInFile']['sha256']);
        $t->same(1211, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][37]['checkedInFile']['bytes']);
        $t->same('duplicate-header-labels', $evidence['generatedTsvNativeStaticEvidence']['samples'][18]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][18]['readerOptions']);
        $t->same('escaped-quote-dialect.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][38]['name']);
        $t->same('1fb627d196a256264e209d4f63d92bf9a40cac52241775abc794679b549fdc4f', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][38]['checkedInFile']['sha256']);
        $t->same(81, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][38]['checkedInFile']['bytes']);
        $t->same('escaped-quote-dialect.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][39]['name']);
        $t->same('858da6b66210ba88c7f74932964abd6a7c35a89464ce20fb855da8d5be4fffe6', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][39]['checkedInFile']['sha256']);
        $t->same(1326, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][39]['checkedInFile']['bytes']);
        $t->same('escaped-quote-dialect', $evidence['generatedTsvNativeStaticEvidence']['samples'][19]['name']);
        $t->same(['quote' => '"', 'escape' => '\\'], $evidence['generatedTsvNativeStaticEvidence']['samples'][19]['readerOptions']);
        $t->same('literal-quote-tab-split.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][40]['name']);
        $t->same('00fa66e3f5a260829bf083772aeea977b1bafda332a62dee7a6b54027cd28bdc', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][40]['checkedInFile']['sha256']);
        $t->same(49, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][40]['checkedInFile']['bytes']);
        $t->same('literal-quote-tab-split.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][41]['name']);
        $t->same('2dcb1348c01e9fd601db48b537d48593b033a8d45ed9641619e569e925f1582e', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][41]['checkedInFile']['sha256']);
        $t->same(1214, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][41]['checkedInFile']['bytes']);
        $t->same('literal-quote-tab-split', $evidence['generatedTsvNativeStaticEvidence']['samples'][20]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][20]['readerOptions']);
        $t->same('leading-blank-whitespace.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][42]['name']);
        $t->same('009966d20c582967816f9721a10b558b07333c88849bff11176b5140e746191e', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][42]['checkedInFile']['sha256']);
        $t->same(3, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][42]['checkedInFile']['bytes']);
        $t->same('leading-blank-whitespace.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][43]['name']);
        $t->same('37517e5f3dc66819f61f5a7bb8ace1921282415f10551d2defa5c3eb0985b570', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][43]['checkedInFile']['sha256']);
        $t->same(3, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][43]['checkedInFile']['bytes']);
        $t->same('leading-blank-whitespace', $evidence['generatedTsvNativeStaticEvidence']['samples'][21]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][21]['readerOptions']);
        $t->same('unquoted-final-formfeed.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][44]['name']);
        $t->same('a329477fc79b06ee10cd8743544b6e627804200a3c411eba3d14db095444bbf4', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][44]['checkedInFile']['sha256']);
        $t->same(12, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][44]['checkedInFile']['bytes']);
        $t->same('unquoted-final-formfeed.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][45]['name']);
        $t->same('a3fbb8cf65627ffdb520bb05437dd79096ccf633cffc8d6537b920738e1db792', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][45]['checkedInFile']['sha256']);
        $t->same(683, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][45]['checkedInFile']['bytes']);
        $t->same('unquoted-final-formfeed', $evidence['generatedTsvNativeStaticEvidence']['samples'][22]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][22]['readerOptions']);
        $t->same('literal-quote-newline-split.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][46]['name']);
        $t->same('c98a20cd63e456e0276d69e70c746980935c1495982f25f3dbec73c03b38bd36', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][46]['checkedInFile']['sha256']);
        $t->same(16, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][46]['checkedInFile']['bytes']);
        $t->same('literal-quote-newline-split.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][47]['name']);
        $t->same('6f2619681b13663971b27c589612e272dd26627a167e9a7a53bee2972899f617', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][47]['checkedInFile']['sha256']);
        $t->same(870, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][47]['checkedInFile']['bytes']);
        $t->same('literal-quote-newline-split', $evidence['generatedTsvNativeStaticEvidence']['samples'][23]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][23]['readerOptions']);
        $t->same('leading-empty-fields.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][48]['name']);
        $t->same('ab4dfed4760d46c5f0dd14b82aa08366dc8b906e748a5e0f7188fa6df4b4d818', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][48]['checkedInFile']['sha256']);
        $t->same(36, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][48]['checkedInFile']['bytes']);
        $t->same('leading-empty-fields.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][49]['name']);
        $t->same('ed543e7867c79895214721849592da8962289f4a8e9d853be8d6bc04f13fc562', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][49]['checkedInFile']['sha256']);
        $t->same(1145, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][49]['checkedInFile']['bytes']);
        $t->same('leading-empty-fields', $evidence['generatedTsvNativeStaticEvidence']['samples'][24]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][24]['readerOptions']);
        $t->same('trailing-empty-fields.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][50]['name']);
        $t->same('3f45d3086a898528498ee69696f28d6ee6876ec891d66806d1609ebc5fc2dcc7', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][50]['checkedInFile']['sha256']);
        $t->same(43, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][50]['checkedInFile']['bytes']);
        $t->same('trailing-empty-fields.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][51]['name']);
        $t->same('5765a3463ad42f0e48295e67e3276bf0d6a0d3d0013e131a492379637a40ebbb', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][51]['checkedInFile']['sha256']);
        $t->same(2363, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][51]['checkedInFile']['bytes']);
        $t->same('trailing-empty-fields', $evidence['generatedTsvNativeStaticEvidence']['samples'][25]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][25]['readerOptions']);
        $t->same('literal-quote-header.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][52]['name']);
        $t->same('bb618e2a1e983dea0842ad93f813bdd7d15e5d00590f868d8d6d2218f92fee3d', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][52]['checkedInFile']['sha256']);
        $t->same(49, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][52]['checkedInFile']['bytes']);
        $t->same('literal-quote-header.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][53]['name']);
        $t->same('ebcd237669082f27a9e47a4602cc46a711a83b158c0e2ffd2006e5ef61c98e64', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][53]['checkedInFile']['sha256']);
        $t->same(2131, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][53]['checkedInFile']['bytes']);
        $t->same('literal-quote-header', $evidence['generatedTsvNativeStaticEvidence']['samples'][26]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][26]['readerOptions']);
        $t->same('markdown-syntax-literal.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][54]['name']);
        $t->same('307f323f10c85aa81a984dcfb7fc8adc4f9f4d17e551064b3276134bae710d9d', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][54]['checkedInFile']['sha256']);
        $t->same(77, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][54]['checkedInFile']['bytes']);
        $t->same('markdown-syntax-literal.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][55]['name']);
        $t->same('b53dd045777e3aeb09537e85c060f30529302052616c31565652ecf15c66f773', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][55]['checkedInFile']['sha256']);
        $t->same(2317, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][55]['checkedInFile']['bytes']);
        $t->same('markdown-syntax-literal', $evidence['generatedTsvNativeStaticEvidence']['samples'][27]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][27]['readerOptions']);
        $t->same('interior-empty-header.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][56]['name']);
        $t->same('1c97e1d22017dd23c5b20edb6fd2049cbfdfd33ec2fbaf52ed1e6135462ffd5c', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][56]['checkedInFile']['sha256']);
        $t->same(41, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][56]['checkedInFile']['bytes']);
        $t->same('interior-empty-header.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][57]['name']);
        $t->same('6facd7076f0094d0ffe96c17e9b9c774dc60b73b38ac3691928939b1a55fd285', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][57]['checkedInFile']['sha256']);
        $t->same(2447, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][57]['checkedInFile']['bytes']);
        $t->same('interior-empty-header', $evidence['generatedTsvNativeStaticEvidence']['samples'][28]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][28]['readerOptions']);
        $t->same('trailing-empty-header.tsv', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][58]['name']);
        $t->same('32f7df1adadfd010a05d9ddd9dbf1705050471da4682cc0d37aa8b81ead666cd', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][58]['checkedInFile']['sha256']);
        $t->same(47, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][58]['checkedInFile']['bytes']);
        $t->same('trailing-empty-header.native', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][59]['name']);
        $t->same('b22eac43664c1917ebbcd2a9b6053cab9cf1afb4785ccefe8cd1602eda3682b3', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][59]['checkedInFile']['sha256']);
        $t->same(2535, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][59]['checkedInFile']['bytes']);
        $t->same('trailing-empty-header', $evidence['generatedTsvNativeStaticEvidence']['samples'][29]['name']);
        $t->same([], $evidence['generatedTsvNativeStaticEvidence']['samples'][29]['readerOptions']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvNativeStaticEvidence($evidence['generatedCsvNativeStaticEvidence']));
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvNativeStaticEvidence($evidence['generatedTsvNativeStaticEvidence']));
        $t->same('valid-checked-in-current-delimited-text-reader-evidence', $evidence['validation']['status']);
        $t->same([], $evidence['validation']['issues']);
        $t->true(in_array('that upstream Haskell/Cabal/Tasty tests were executed', $evidence['claimBoundaries']['doesNotAssert'], true));
    },
    'executes generated csv native parity evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = DelimitedTextUpstreamReaderEvidence::generatedCsvNativeParityEvidence($repoRoot);

        $t->same('executable-generated-csv-native-parity-evidence', $evidence['kind']);
        $t->same('generated-csv-native-parity', $evidence['evidenceKind']);
        $t->same('csv', $evidence['reader']);
        $t->same(2, $evidence['csvDirectFixtureDenominator']);
        $t->same(44, $evidence['sampleCount']);
        $t->same(44, $evidence['comparedSampleCount']);
        $t->same(0, $evidence['parseFailureCount']);
        $t->same(44, $evidence['generatedNativeMatchCount']);
        $t->same(0, $evidence['generatedNativeMismatchCount']);
        $t->same(100.0, $evidence['generatedNativeMatchPercent']);
        $t->same(44, $evidence['staticFixtureBindingValidCount']);
        $t->same(0, $evidence['staticFixtureBindingInvalidCount']);
        $t->same(array_fill(0, 44, 'valid-generated-csv-native-sample-static-binding'), array_column($evidence['samples'], 'staticFixtureBindingStatus'));
        $t->same('generated-csv-native-parity-observed-not-upstream-fixture', $evidence['parityStatus']);
        $t->same('matched', $evidence['samples'][0]['status']);
        $t->same('quoted-multiline', $evidence['samples'][0]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv', $evidence['samples'][0]['inputPath']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][0]['staticFixtureBinding']['kind']);
        $t->same('quoted-multiline', $evidence['samples'][0]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][0]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][0]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.csv', $evidence['samples'][0]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-multiline.native', $evidence['samples'][0]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][0]['rowCount']);
        $t->same(4, $evidence['samples'][0]['columnCount']);
        $t->same('matched', $evidence['samples'][1]['status']);
        $t->same('post-delimiter-space', $evidence['samples'][1]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-space.csv', $evidence['samples'][1]['inputPath']);
        $t->same(4, $evidence['samples'][1]['rowCount']);
        $t->same(3, $evidence['samples'][1]['columnCount']);
        $t->same('matched', $evidence['samples'][2]['status']);
        $t->same('backslash-escaped-quote', $evidence['samples'][2]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.csv', $evidence['samples'][2]['inputPath']);
        $t->same(['escape' => '\\', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-quote.csv'], $evidence['samples'][2]['readerOptions']);
        $t->same(3, $evidence['samples'][2]['rowCount']);
        $t->same(2, $evidence['samples'][2]['columnCount']);
        $t->same('matched', $evidence['samples'][3]['status']);
        $t->same('quoted-linebreak', $evidence['samples'][3]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.csv', $evidence['samples'][3]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-linebreak.csv'], $evidence['samples'][3]['readerOptions']);
        $t->same(3, $evidence['samples'][3]['rowCount']);
        $t->same(3, $evidence['samples'][3]['columnCount']);
        $t->same('matched', $evidence['samples'][4]['status']);
        $t->same('no-header-ragged', $evidence['samples'][4]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.csv', $evidence['samples'][4]['inputPath']);
        $t->same(['header' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-ragged.csv'], $evidence['samples'][4]['readerOptions']);
        $t->same(3, $evidence['samples'][4]['rowCount']);
        $t->same(4, $evidence['samples'][4]['columnCount']);
        $t->same('matched', $evidence['samples'][5]['status']);
        $t->same('bom-leading-whitespace', $evidence['samples'][5]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.csv', $evidence['samples'][5]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/bom-leading-whitespace.csv'], $evidence['samples'][5]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][5]['staticFixtureBinding']['kind']);
        $t->same('bom-leading-whitespace', $evidence['samples'][5]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][5]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][5]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same(3, $evidence['samples'][5]['rowCount']);
        $t->same(3, $evidence['samples'][5]['columnCount']);
        $t->same('matched', $evidence['samples'][6]['status']);
        $t->same('text-after-closing-quote', $evidence['samples'][6]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv', $evidence['samples'][6]['inputPath']);
        $t->same(['strictParsing' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv'], $evidence['samples'][6]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][6]['staticFixtureBinding']['kind']);
        $t->same('text-after-closing-quote', $evidence['samples'][6]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][6]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][6]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same(3, $evidence['samples'][6]['rowCount']);
        $t->same(3, $evidence['samples'][6]['columnCount']);
        $t->same('matched', $evidence['samples'][7]['status']);
        $t->same('trailing-empty-fields', $evidence['samples'][7]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv', $evidence['samples'][7]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv'], $evidence['samples'][7]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][7]['staticFixtureBinding']['kind']);
        $t->same('trailing-empty-fields', $evidence['samples'][7]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][7]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][7]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.csv', $evidence['samples'][7]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/trailing-empty-fields.native', $evidence['samples'][7]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][7]['rowCount']);
        $t->same(4, $evidence['samples'][7]['columnCount']);
        $t->same('matched', $evidence['samples'][8]['status']);
        $t->same('crlf-rows', $evidence['samples'][8]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv', $evidence['samples'][8]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv'], $evidence['samples'][8]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][8]['staticFixtureBinding']['kind']);
        $t->same('crlf-rows', $evidence['samples'][8]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][8]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][8]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.csv', $evidence['samples'][8]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/crlf-rows.native', $evidence['samples'][8]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][8]['rowCount']);
        $t->same(3, $evidence['samples'][8]['columnCount']);
        $t->same('matched', $evidence['samples'][9]['status']);
        $t->same('unquoted-space-empty-quoted', $evidence['samples'][9]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv', $evidence['samples'][9]['inputPath']);
        $t->same(['strictParsing' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv'], $evidence['samples'][9]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][9]['staticFixtureBinding']['kind']);
        $t->same('unquoted-space-empty-quoted', $evidence['samples'][9]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][9]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][9]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv', $evidence['samples'][9]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.native', $evidence['samples'][9]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][9]['rowCount']);
        $t->same(4, $evidence['samples'][9]['columnCount']);
        $t->same('matched', $evidence['samples'][10]['status']);
        $t->same('comment-looking-data', $evidence['samples'][10]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv', $evidence['samples'][10]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv'], $evidence['samples'][10]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][10]['staticFixtureBinding']['kind']);
        $t->same('comment-looking-data', $evidence['samples'][10]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][10]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][10]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.csv', $evidence['samples'][10]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/comment-looking-data.native', $evidence['samples'][10]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][10]['rowCount']);
        $t->same(3, $evidence['samples'][10]['columnCount']);
        $t->same('matched', $evidence['samples'][11]['status']);
        $t->same('no-header-edge-delimiters', $evidence['samples'][11]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv', $evidence['samples'][11]['inputPath']);
        $t->same(['header' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv'], $evidence['samples'][11]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][11]['staticFixtureBinding']['kind']);
        $t->same('no-header-edge-delimiters', $evidence['samples'][11]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][11]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][11]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.csv', $evidence['samples'][11]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/no-header-edge-delimiters.native', $evidence['samples'][11]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][11]['rowCount']);
        $t->same(4, $evidence['samples'][11]['columnCount']);
        $t->same('matched', $evidence['samples'][12]['status']);
        $t->same('single-quote-dialect', $evidence['samples'][12]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv', $evidence['samples'][12]['inputPath']);
        $t->same(['quote' => '\'', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv'], $evidence['samples'][12]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][12]['staticFixtureBinding']['kind']);
        $t->same('single-quote-dialect', $evidence['samples'][12]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][12]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][12]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.csv', $evidence['samples'][12]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/single-quote-dialect.native', $evidence['samples'][12]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][12]['rowCount']);
        $t->same(3, $evidence['samples'][12]['columnCount']);
        $t->same('matched', $evidence['samples'][13]['status']);
        $t->same('semicolon-delimiter-multiline-cell', $evidence['samples'][13]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv', $evidence['samples'][13]['inputPath']);
        $t->same(['delimiter' => 'semicolon', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv'], $evidence['samples'][13]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][13]['staticFixtureBinding']['kind']);
        $t->same('semicolon-delimiter-multiline-cell', $evidence['samples'][13]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][13]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][13]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.csv', $evidence['samples'][13]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/semicolon-delimiter-multiline-cell.native', $evidence['samples'][13]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][13]['rowCount']);
        $t->same(4, $evidence['samples'][13]['columnCount']);
        $t->same('matched', $evidence['samples'][14]['status']);
        $t->same('cr-only-rows', $evidence['samples'][14]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv', $evidence['samples'][14]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv'], $evidence['samples'][14]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][14]['staticFixtureBinding']['kind']);
        $t->same('cr-only-rows', $evidence['samples'][14]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][14]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][14]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.csv', $evidence['samples'][14]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/cr-only-rows.native', $evidence['samples'][14]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(1, $evidence['samples'][14]['rowCount']);
        $t->same(7, $evidence['samples'][14]['columnCount']);
        $t->same('matched', $evidence['samples'][15]['status']);
        $t->same('unterminated-quote-eof', $evidence['samples'][15]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv', $evidence['samples'][15]['inputPath']);
        $t->same(['strictParsing' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv'], $evidence['samples'][15]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][15]['staticFixtureBinding']['kind']);
        $t->same('unterminated-quote-eof', $evidence['samples'][15]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][15]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][15]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.csv', $evidence['samples'][15]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unterminated-quote-eof.native', $evidence['samples'][15]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(2, $evidence['samples'][15]['rowCount']);
        $t->same(3, $evidence['samples'][15]['columnCount']);
        $t->same('matched', $evidence['samples'][16]['status']);
        $t->same('duplicate-header-labels', $evidence['samples'][16]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv', $evidence['samples'][16]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv'], $evidence['samples'][16]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][16]['staticFixtureBinding']['kind']);
        $t->same('duplicate-header-labels', $evidence['samples'][16]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][16]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][16]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.csv', $evidence['samples'][16]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/duplicate-header-labels.native', $evidence['samples'][16]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][16]['rowCount']);
        $t->same(3, $evidence['samples'][16]['columnCount']);
        $t->same('matched', $evidence['samples'][17]['status']);
        $t->same('keep-space-after-comma', $evidence['samples'][17]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv', $evidence['samples'][17]['inputPath']);
        $t->same(['keepSpace' => true, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv'], $evidence['samples'][17]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][17]['staticFixtureBinding']['kind']);
        $t->same('keep-space-after-comma', $evidence['samples'][17]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][17]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][17]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.csv', $evidence['samples'][17]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/keep-space-after-comma.native', $evidence['samples'][17]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][17]['rowCount']);
        $t->same(3, $evidence['samples'][17]['columnCount']);
        $t->same('matched', $evidence['samples'][18]['status']);
        $t->same('space-delimiter-single-quote', $evidence['samples'][18]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv', $evidence['samples'][18]['inputPath']);
        $t->same(['delimiter' => 'space', 'quote' => '\'', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv'], $evidence['samples'][18]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][18]['staticFixtureBinding']['kind']);
        $t->same('space-delimiter-single-quote', $evidence['samples'][18]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][18]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][18]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.csv', $evidence['samples'][18]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/space-delimiter-single-quote.native', $evidence['samples'][18]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][18]['rowCount']);
        $t->same(3, $evidence['samples'][18]['columnCount']);
        $t->same('matched', $evidence['samples'][19]['status']);
        $t->same('blank-row-skipped', $evidence['samples'][19]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv', $evidence['samples'][19]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv'], $evidence['samples'][19]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][19]['staticFixtureBinding']['kind']);
        $t->same('blank-row-skipped', $evidence['samples'][19]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][19]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][19]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.csv', $evidence['samples'][19]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-row-skipped.native', $evidence['samples'][19]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][19]['rowCount']);
        $t->same(3, $evidence['samples'][19]['columnCount']);
        $last = $evidence['samples'][33];
        $t->same('matched', $last['status']);
        $t->same('leading-empty-fields', $last['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.csv', $last['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.csv'], $last['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $last['staticFixtureBinding']['kind']);
        $t->same('leading-empty-fields', $last['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $last['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $last['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.csv', $last['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-empty-fields.native', $last['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $last['rowCount']);
        $t->same(3, $last['columnCount']);
        $newLast = $evidence['samples'][34];
        $t->same('matched', $newLast['status']);
        $t->same('quoted-header-fields', $newLast['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.csv', $newLast['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.csv'], $newLast['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $newLast['staticFixtureBinding']['kind']);
        $t->same('quoted-header-fields', $newLast['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $newLast['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $newLast['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.csv', $newLast['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-header-fields.native', $newLast['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $newLast['rowCount']);
        $t->same(3, $newLast['columnCount']);
        $appended = $evidence['samples'][35];
        $t->same('matched', $appended['status']);
        $t->same('unquoted-tab-cell', $appended['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.csv', $appended['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.csv'], $appended['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $appended['staticFixtureBinding']['kind']);
        $t->same('unquoted-tab-cell', $appended['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $appended['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $appended['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.csv', $appended['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-tab-cell.native', $appended['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(2, $appended['rowCount']);
        $t->same(2, $appended['columnCount']);
        $t->same('matched', $evidence['samples'][20]['status']);
        $t->same('backslash-escaped-nonquote', $evidence['samples'][20]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.csv', $evidence['samples'][20]['inputPath']);
        $t->same(['escape' => '\\', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.csv'], $evidence['samples'][20]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][20]['staticFixtureBinding']['kind']);
        $t->same('backslash-escaped-nonquote', $evidence['samples'][20]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][20]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][20]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.csv', $evidence['samples'][20]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/backslash-escaped-nonquote.native', $evidence['samples'][20]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][20]['rowCount']);
        $t->same(3, $evidence['samples'][20]['columnCount']);
        $t->same('matched', $evidence['samples'][21]['status']);
        $t->same('pipe-delimiter-quoted-field', $evidence['samples'][21]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.csv', $evidence['samples'][21]['inputPath']);
        $t->same(['delimiter' => 'pipe', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.csv'], $evidence['samples'][21]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][21]['staticFixtureBinding']['kind']);
        $t->same('pipe-delimiter-quoted-field', $evidence['samples'][21]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][21]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][21]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.csv', $evidence['samples'][21]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/pipe-delimiter-quoted-field.native', $evidence['samples'][21]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][21]['rowCount']);
        $t->same(3, $evidence['samples'][21]['columnCount']);
        $t->same('matched', $evidence['samples'][22]['status']);
        $t->same('quote-disabled-literal', $evidence['samples'][22]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.csv', $evidence['samples'][22]['inputPath']);
        $t->same(['quote' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.csv'], $evidence['samples'][22]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][22]['staticFixtureBinding']['kind']);
        $t->same('quote-disabled-literal', $evidence['samples'][22]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][22]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][22]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.csv', $evidence['samples'][22]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quote-disabled-literal.native', $evidence['samples'][22]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][22]['rowCount']);
        $t->same(3, $evidence['samples'][22]['columnCount']);
        $t->same('matched', $evidence['samples'][23]['status']);
        $t->same('blank-input', $evidence['samples'][23]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.csv', $evidence['samples'][23]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.csv'], $evidence['samples'][23]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][23]['staticFixtureBinding']['kind']);
        $t->same('blank-input', $evidence['samples'][23]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][23]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][23]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.csv', $evidence['samples'][23]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-input.native', $evidence['samples'][23]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(null, $evidence['samples'][23]['rowCount']);
        $t->same(null, $evidence['samples'][23]['columnCount']);
        $t->same('matched', $evidence['samples'][24]['status']);
        $t->same('unicode-safe', $evidence['samples'][24]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.csv', $evidence['samples'][24]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.csv'], $evidence['samples'][24]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][24]['staticFixtureBinding']['kind']);
        $t->same('unicode-safe', $evidence['samples'][24]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][24]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][24]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.csv', $evidence['samples'][24]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unicode-safe.native', $evidence['samples'][24]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][24]['rowCount']);
        $t->same(3, $evidence['samples'][24]['columnCount']);
        $t->same('matched', $evidence['samples'][25]['status']);
        $t->same('quote-in-unquoted-field', $evidence['samples'][25]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.csv', $evidence['samples'][25]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.csv'], $evidence['samples'][25]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][25]['staticFixtureBinding']['kind']);
        $t->same('quote-in-unquoted-field', $evidence['samples'][25]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][25]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][25]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.csv', $evidence['samples'][25]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quote-in-unquoted-field.native', $evidence['samples'][25]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][25]['rowCount']);
        $t->same(3, $evidence['samples'][25]['columnCount']);
        $t->same('matched', $evidence['samples'][26]['status']);
        $t->same('header-only', $evidence['samples'][26]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/header-only.csv', $evidence['samples'][26]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/header-only.csv'], $evidence['samples'][26]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][26]['staticFixtureBinding']['kind']);
        $t->same('header-only', $evidence['samples'][26]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][26]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][26]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/header-only.csv', $evidence['samples'][26]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/header-only.native', $evidence['samples'][26]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(1, $evidence['samples'][26]['rowCount']);
        $t->same(3, $evidence['samples'][26]['columnCount']);
        $t->same('matched', $evidence['samples'][27]['status']);
        $t->same('leading-whitespace-record', $evidence['samples'][27]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.csv', $evidence['samples'][27]['inputPath']);
        $t->same(['strictParsing' => true, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.csv'], $evidence['samples'][27]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][27]['staticFixtureBinding']['kind']);
        $t->same('leading-whitespace-record', $evidence['samples'][27]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][27]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][27]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.csv', $evidence['samples'][27]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-record.native', $evidence['samples'][27]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][27]['rowCount']);
        $t->same(1, $evidence['samples'][27]['columnCount']);
        $t->same('matched', $evidence['samples'][28]['status']);
        $t->same('leading-blank-whitespace', $evidence['samples'][28]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.csv', $evidence['samples'][28]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.csv'], $evidence['samples'][28]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][28]['staticFixtureBinding']['kind']);
        $t->same('leading-blank-whitespace', $evidence['samples'][28]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][28]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][28]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.csv', $evidence['samples'][28]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-blank-whitespace.native', $evidence['samples'][28]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(null, $evidence['samples'][28]['rowCount']);
        $t->same(null, $evidence['samples'][28]['columnCount']);
        $t->same('matched', $evidence['samples'][29]['status']);
        $t->same('quoted-final-vtab-whitespace', $evidence['samples'][29]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.csv', $evidence['samples'][29]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.csv'], $evidence['samples'][29]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][29]['staticFixtureBinding']['kind']);
        $t->same('quoted-final-vtab-whitespace', $evidence['samples'][29]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][29]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][29]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.csv', $evidence['samples'][29]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-final-vtab-whitespace.native', $evidence['samples'][29]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(2, $evidence['samples'][29]['rowCount']);
        $t->same(2, $evidence['samples'][29]['columnCount']);
        $t->same('matched', $evidence['samples'][30]['status']);
        $t->same('unquoted-final-formfeed', $evidence['samples'][30]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.csv', $evidence['samples'][30]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.csv'], $evidence['samples'][30]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][30]['staticFixtureBinding']['kind']);
        $t->same('unquoted-final-formfeed', $evidence['samples'][30]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][30]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][30]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.csv', $evidence['samples'][30]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-final-formfeed.native', $evidence['samples'][30]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(2, $evidence['samples'][30]['rowCount']);
        $t->same(2, $evidence['samples'][30]['columnCount']);
        $t->same('matched', $evidence['samples'][31]['status']);
        $t->same('space-only-record', $evidence['samples'][31]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.csv', $evidence['samples'][31]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.csv'], $evidence['samples'][31]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][31]['staticFixtureBinding']['kind']);
        $t->same('space-only-record', $evidence['samples'][31]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][31]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][31]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.csv', $evidence['samples'][31]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/space-only-record.native', $evidence['samples'][31]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(1, $evidence['samples'][31]['rowCount']);
        $t->same(1, $evidence['samples'][31]['columnCount']);
        $t->same('matched', $evidence['samples'][36]['status']);
        $t->same('quoted-empty-fields', $evidence['samples'][36]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.csv', $evidence['samples'][36]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.csv'], $evidence['samples'][36]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][36]['staticFixtureBinding']['kind']);
        $t->same('quoted-empty-fields', $evidence['samples'][36]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][36]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][36]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.csv', $evidence['samples'][36]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-empty-fields.native', $evidence['samples'][36]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][36]['rowCount']);
        $t->same(4, $evidence['samples'][36]['columnCount']);
        $t->same('matched', $evidence['samples'][37]['status']);
        $t->same('leading-whitespace-before-quote', $evidence['samples'][37]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.csv', $evidence['samples'][37]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.csv'], $evidence['samples'][37]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][37]['staticFixtureBinding']['kind']);
        $t->same('leading-whitespace-before-quote', $evidence['samples'][37]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][37]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][37]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.csv', $evidence['samples'][37]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/leading-whitespace-before-quote.native', $evidence['samples'][37]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][37]['rowCount']);
        $t->same(3, $evidence['samples'][37]['columnCount']);
        $t->same('matched', $evidence['samples'][38]['status']);
        $t->same('post-delimiter-tab', $evidence['samples'][38]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.csv', $evidence['samples'][38]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.csv'], $evidence['samples'][38]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][38]['staticFixtureBinding']['kind']);
        $t->same('post-delimiter-tab', $evidence['samples'][38]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][38]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][38]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.csv', $evidence['samples'][38]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/post-delimiter-tab.native', $evidence['samples'][38]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][38]['rowCount']);
        $t->same(3, $evidence['samples'][38]['columnCount']);
        $t->same('matched', $evidence['samples'][39]['status']);
        $t->same('quoted-blank-line-cell', $evidence['samples'][39]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.csv', $evidence['samples'][39]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.csv'], $evidence['samples'][39]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][39]['staticFixtureBinding']['kind']);
        $t->same('quoted-blank-line-cell', $evidence['samples'][39]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][39]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][39]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.csv', $evidence['samples'][39]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/quoted-blank-line-cell.native', $evidence['samples'][39]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(2, $evidence['samples'][39]['rowCount']);
        $t->same(3, $evidence['samples'][39]['columnCount']);
        $t->same('matched', $evidence['samples'][40]['status']);
        $t->same('blank-leading-header', $evidence['samples'][40]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.csv', $evidence['samples'][40]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.csv'], $evidence['samples'][40]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][40]['staticFixtureBinding']['kind']);
        $t->same('blank-leading-header', $evidence['samples'][40]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][40]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][40]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.csv', $evidence['samples'][40]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/blank-leading-header.native', $evidence['samples'][40]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][40]['rowCount']);
        $t->same(3, $evidence['samples'][40]['columnCount']);
        $t->same('matched', $evidence['samples'][41]['status']);
        $t->same('pre-delimiter-space', $evidence['samples'][41]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.csv', $evidence['samples'][41]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.csv'], $evidence['samples'][41]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][41]['staticFixtureBinding']['kind']);
        $t->same('pre-delimiter-space', $evidence['samples'][41]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][41]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][41]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.csv', $evidence['samples'][41]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/pre-delimiter-space.native', $evidence['samples'][41]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][41]['rowCount']);
        $t->same(3, $evidence['samples'][41]['columnCount']);
        $t->same('matched', $evidence['samples'][42]['status']);
        $t->same('markdown-syntax-literal', $evidence['samples'][42]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.csv', $evidence['samples'][42]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.csv'], $evidence['samples'][42]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][42]['staticFixtureBinding']['kind']);
        $t->same('markdown-syntax-literal', $evidence['samples'][42]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][42]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][42]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.csv', $evidence['samples'][42]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/markdown-syntax-literal.native', $evidence['samples'][42]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][42]['rowCount']);
        $t->same(3, $evidence['samples'][42]['columnCount']);
        $t->same('matched', $evidence['samples'][43]['status']);
        $t->same('formula-looking-literals', $evidence['samples'][43]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.csv', $evidence['samples'][43]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.csv'], $evidence['samples'][43]['readerOptions']);
        $t->same('generated-csv-native-sample-static-fixture-binding', $evidence['samples'][43]['staticFixtureBinding']['kind']);
        $t->same('formula-looking-literals', $evidence['samples'][43]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][43]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][43]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.csv', $evidence['samples'][43]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-csv-reader/formula-looking-literals.native', $evidence['samples'][43]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][43]['rowCount']);
        $t->same(3, $evidence['samples'][43]['columnCount']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvNativeParity($evidence));
        $t->true(in_array('that the generated CSV samples are upstream command fixtures', $evidence['claimBoundaries']['doesNotAssert'], true));
    },
    'executes generated tsv native parity evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $evidence = DelimitedTextUpstreamReaderEvidence::generatedTsvNativeParityEvidence($repoRoot);

        $t->same('executable-generated-tsv-native-parity-evidence', $evidence['kind']);
        $t->same('generated-tsv-native-parity', $evidence['evidenceKind']);
        $t->same('tsv', $evidence['reader']);
        $t->same(0, $evidence['tsvDirectFixtureDenominator']);
        $t->same(30, $evidence['sampleCount']);
        $t->same(30, $evidence['comparedSampleCount']);
        $t->same(0, $evidence['parseFailureCount']);
        $t->same(30, $evidence['generatedNativeMatchCount']);
        $t->same(0, $evidence['generatedNativeMismatchCount']);
        $t->same(100.0, $evidence['generatedNativeMatchPercent']);
        $t->same(30, $evidence['staticFixtureBindingValidCount']);
        $t->same(0, $evidence['staticFixtureBindingInvalidCount']);
        $t->same(array_fill(0, 30, 'valid-generated-tsv-native-sample-static-binding'), array_column($evidence['samples'], 'staticFixtureBindingStatus'));
        $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $evidence['parityStatus']);
        $t->same('matched', $evidence['samples'][0]['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv', $evidence['samples'][0]['inputPath']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][0]['staticFixtureBinding']['kind']);
        $t->same('simple', $evidence['samples'][0]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][0]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][0]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/simple.tsv', $evidence['samples'][0]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/simple.native', $evidence['samples'][0]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][0]['columnCount']);
        $t->same('matched', $evidence['samples'][1]['status']);
        $t->same('quote-trailing', $evidence['samples'][1]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/quote-trailing.tsv', $evidence['samples'][1]['inputPath']);
        $t->same(4, $evidence['samples'][1]['rowCount']);
        $t->same(4, $evidence['samples'][1]['columnCount']);
        $t->same('matched', $evidence['samples'][2]['status']);
        $t->same('unicode-safe', $evidence['samples'][2]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/unicode-safe.tsv', $evidence['samples'][2]['inputPath']);
        $t->same(3, $evidence['samples'][2]['rowCount']);
        $t->same(3, $evidence['samples'][2]['columnCount']);
        $t->same('matched', $evidence['samples'][3]['status']);
        $t->same('ragged-blank-fields', $evidence['samples'][3]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/ragged-blank-fields.tsv', $evidence['samples'][3]['inputPath']);
        $t->same(5, $evidence['samples'][3]['rowCount']);
        $t->same(3, $evidence['samples'][3]['columnCount']);
        $t->same('matched', $evidence['samples'][4]['status']);
        $t->same('no-header', $evidence['samples'][4]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.tsv', $evidence['samples'][4]['inputPath']);
        $t->same(['header' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header.tsv'], $evidence['samples'][4]['readerOptions']);
        $t->same(3, $evidence['samples'][4]['rowCount']);
        $t->same(3, $evidence['samples'][4]['columnCount']);
        $t->same('matched', $evidence['samples'][5]['status']);
        $t->same('bom-leading-whitespace', $evidence['samples'][5]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv', $evidence['samples'][5]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv'], $evidence['samples'][5]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][5]['staticFixtureBinding']['kind']);
        $t->same('bom-leading-whitespace', $evidence['samples'][5]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][5]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][5]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.tsv', $evidence['samples'][5]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/bom-leading-whitespace.native', $evidence['samples'][5]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][5]['rowCount']);
        $t->same(3, $evidence['samples'][5]['columnCount']);
        $t->same('matched', $evidence['samples'][6]['status']);
        $t->same('blank-row-literal-punctuation', $evidence['samples'][6]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv', $evidence['samples'][6]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv'], $evidence['samples'][6]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][6]['staticFixtureBinding']['kind']);
        $t->same('blank-row-literal-punctuation', $evidence['samples'][6]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][6]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][6]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.tsv', $evidence['samples'][6]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-row-literal-punctuation.native', $evidence['samples'][6]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][6]['rowCount']);
        $t->same(3, $evidence['samples'][6]['columnCount']);
        $t->same('matched', $evidence['samples'][7]['status']);
        $t->same('comment-looking-data', $evidence['samples'][7]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv', $evidence['samples'][7]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv'], $evidence['samples'][7]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][7]['staticFixtureBinding']['kind']);
        $t->same('comment-looking-data', $evidence['samples'][7]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][7]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][7]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.tsv', $evidence['samples'][7]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/comment-looking-data.native', $evidence['samples'][7]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][7]['rowCount']);
        $t->same(3, $evidence['samples'][7]['columnCount']);
        $t->same('matched', $evidence['samples'][8]['status']);
        $t->same('no-header-edge-delimiters', $evidence['samples'][8]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv', $evidence['samples'][8]['inputPath']);
        $t->same(['header' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv'], $evidence['samples'][8]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][8]['staticFixtureBinding']['kind']);
        $t->same('no-header-edge-delimiters', $evidence['samples'][8]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][8]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][8]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.tsv', $evidence['samples'][8]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-edge-delimiters.native', $evidence['samples'][8]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][8]['rowCount']);
        $t->same(4, $evidence['samples'][8]['columnCount']);
        $t->same('matched', $evidence['samples'][9]['status']);
        $t->same('csv-quoted-literal', $evidence['samples'][9]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv', $evidence['samples'][9]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv'], $evidence['samples'][9]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][9]['staticFixtureBinding']['kind']);
        $t->same('csv-quoted-literal', $evidence['samples'][9]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][9]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][9]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.tsv', $evidence['samples'][9]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/csv-quoted-literal.native', $evidence['samples'][9]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][9]['rowCount']);
        $t->same(3, $evidence['samples'][9]['columnCount']);
        $t->same('matched', $evidence['samples'][10]['status']);
        $t->same('keep-space-after-tab', $evidence['samples'][10]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv', $evidence['samples'][10]['inputPath']);
        $t->same(['keepSpace' => true, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv'], $evidence['samples'][10]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][10]['staticFixtureBinding']['kind']);
        $t->same('keep-space-after-tab', $evidence['samples'][10]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][10]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][10]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.tsv', $evidence['samples'][10]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/keep-space-after-tab.native', $evidence['samples'][10]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][10]['rowCount']);
        $t->same(3, $evidence['samples'][10]['columnCount']);
        $t->same('matched', $evidence['samples'][11]['status']);
        $t->same('crlf-rows', $evidence['samples'][11]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv', $evidence['samples'][11]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv'], $evidence['samples'][11]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][11]['staticFixtureBinding']['kind']);
        $t->same('crlf-rows', $evidence['samples'][11]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][11]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][11]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.tsv', $evidence['samples'][11]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/crlf-rows.native', $evidence['samples'][11]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][11]['rowCount']);
        $t->same(3, $evidence['samples'][11]['columnCount']);
        $t->same('matched', $evidence['samples'][12]['status']);
        $t->same('quoted-tabs-and-newlines', $evidence['samples'][12]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv', $evidence['samples'][12]['inputPath']);
        $t->same(['quote' => '"', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv'], $evidence['samples'][12]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][12]['staticFixtureBinding']['kind']);
        $t->same('quoted-tabs-and-newlines', $evidence['samples'][12]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][12]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][12]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.tsv', $evidence['samples'][12]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/quoted-tabs-and-newlines.native', $evidence['samples'][12]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][12]['rowCount']);
        $t->same(3, $evidence['samples'][12]['columnCount']);
        $t->same('matched', $evidence['samples'][13]['status']);
        $t->same('blank-leading-header', $evidence['samples'][13]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv', $evidence['samples'][13]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv'], $evidence['samples'][13]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][13]['staticFixtureBinding']['kind']);
        $t->same('blank-leading-header', $evidence['samples'][13]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][13]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][13]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.tsv', $evidence['samples'][13]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-leading-header.native', $evidence['samples'][13]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][13]['rowCount']);
        $t->same(2, $evidence['samples'][13]['columnCount']);
        $t->same('matched', $evidence['samples'][14]['status']);
        $t->same('basic-status', $evidence['samples'][14]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv', $evidence['samples'][14]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv'], $evidence['samples'][14]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][14]['staticFixtureBinding']['kind']);
        $t->same('basic-status', $evidence['samples'][14]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][14]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][14]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.tsv', $evidence['samples'][14]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/basic-status.native', $evidence['samples'][14]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][14]['rowCount']);
        $t->same(3, $evidence['samples'][14]['columnCount']);
        $t->same('matched', $evidence['samples'][15]['status']);
        $t->same('header-only', $evidence['samples'][15]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.tsv', $evidence['samples'][15]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.tsv'], $evidence['samples'][15]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][15]['staticFixtureBinding']['kind']);
        $t->same('header-only', $evidence['samples'][15]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][15]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][15]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.tsv', $evidence['samples'][15]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/header-only.native', $evidence['samples'][15]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(1, $evidence['samples'][15]['rowCount']);
        $t->same(3, $evidence['samples'][15]['columnCount']);
        $t->same('matched', $evidence['samples'][16]['status']);
        $t->same('no-header-internal-trailing-empty', $evidence['samples'][16]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.tsv', $evidence['samples'][16]['inputPath']);
        $t->same(['header' => false, 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.tsv'], $evidence['samples'][16]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][16]['staticFixtureBinding']['kind']);
        $t->same('no-header-internal-trailing-empty', $evidence['samples'][16]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][16]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][16]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.tsv', $evidence['samples'][16]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/no-header-internal-trailing-empty.native', $evidence['samples'][16]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][16]['rowCount']);
        $t->same(4, $evidence['samples'][16]['columnCount']);
        $t->same('matched', $evidence['samples'][17]['status']);
        $t->same('blank-input', $evidence['samples'][17]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.tsv', $evidence['samples'][17]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.tsv'], $evidence['samples'][17]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][17]['staticFixtureBinding']['kind']);
        $t->same('blank-input', $evidence['samples'][17]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][17]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][17]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.tsv', $evidence['samples'][17]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/blank-input.native', $evidence['samples'][17]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(null, $evidence['samples'][17]['rowCount']);
        $t->same(null, $evidence['samples'][17]['columnCount']);
        $t->same('matched', $evidence['samples'][18]['status']);
        $t->same('duplicate-header-labels', $evidence['samples'][18]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.tsv', $evidence['samples'][18]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.tsv'], $evidence['samples'][18]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][18]['staticFixtureBinding']['kind']);
        $t->same('duplicate-header-labels', $evidence['samples'][18]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][18]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][18]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.tsv', $evidence['samples'][18]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/duplicate-header-labels.native', $evidence['samples'][18]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][18]['rowCount']);
        $t->same(3, $evidence['samples'][18]['columnCount']);
        $t->same('matched', $evidence['samples'][19]['status']);
        $t->same('escaped-quote-dialect', $evidence['samples'][19]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.tsv', $evidence['samples'][19]['inputPath']);
        $t->same(['quote' => '"', 'escape' => '\\', 'sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.tsv'], $evidence['samples'][19]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][19]['staticFixtureBinding']['kind']);
        $t->same('escaped-quote-dialect', $evidence['samples'][19]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][19]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][19]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.tsv', $evidence['samples'][19]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/escaped-quote-dialect.native', $evidence['samples'][19]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][19]['rowCount']);
        $t->same(3, $evidence['samples'][19]['columnCount']);
        $t->same('matched', $evidence['samples'][20]['status']);
        $t->same('literal-quote-tab-split', $evidence['samples'][20]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.tsv', $evidence['samples'][20]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.tsv'], $evidence['samples'][20]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][20]['staticFixtureBinding']['kind']);
        $t->same('literal-quote-tab-split', $evidence['samples'][20]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][20]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][20]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.tsv', $evidence['samples'][20]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-tab-split.native', $evidence['samples'][20]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][20]['rowCount']);
        $t->same(3, $evidence['samples'][20]['columnCount']);
        $t->same('matched', $evidence['samples'][21]['status']);
        $t->same('leading-blank-whitespace', $evidence['samples'][21]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.tsv', $evidence['samples'][21]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.tsv'], $evidence['samples'][21]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][21]['staticFixtureBinding']['kind']);
        $t->same('leading-blank-whitespace', $evidence['samples'][21]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][21]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][21]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.tsv', $evidence['samples'][21]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/leading-blank-whitespace.native', $evidence['samples'][21]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(null, $evidence['samples'][21]['rowCount']);
        $t->same(null, $evidence['samples'][21]['columnCount']);
        $t->same('matched', $evidence['samples'][22]['status']);
        $t->same('unquoted-final-formfeed', $evidence['samples'][22]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.tsv', $evidence['samples'][22]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.tsv'], $evidence['samples'][22]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][22]['staticFixtureBinding']['kind']);
        $t->same('unquoted-final-formfeed', $evidence['samples'][22]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][22]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][22]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.tsv', $evidence['samples'][22]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/unquoted-final-formfeed.native', $evidence['samples'][22]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(2, $evidence['samples'][22]['rowCount']);
        $t->same(2, $evidence['samples'][22]['columnCount']);
        $t->same('matched', $evidence['samples'][23]['status']);
        $t->same('literal-quote-newline-split', $evidence['samples'][23]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.tsv', $evidence['samples'][23]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.tsv'], $evidence['samples'][23]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][23]['staticFixtureBinding']['kind']);
        $t->same('literal-quote-newline-split', $evidence['samples'][23]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][23]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][23]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.tsv', $evidence['samples'][23]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-newline-split.native', $evidence['samples'][23]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][23]['rowCount']);
        $t->same(2, $evidence['samples'][23]['columnCount']);
        $t->same('matched', $evidence['samples'][24]['status']);
        $t->same('leading-empty-fields', $evidence['samples'][24]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.tsv', $evidence['samples'][24]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.tsv'], $evidence['samples'][24]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][24]['staticFixtureBinding']['kind']);
        $t->same('leading-empty-fields', $evidence['samples'][24]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][24]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][24]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.tsv', $evidence['samples'][24]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/leading-empty-fields.native', $evidence['samples'][24]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][24]['rowCount']);
        $t->same(3, $evidence['samples'][24]['columnCount']);
        $t->same('matched', $evidence['samples'][25]['status']);
        $t->same('trailing-empty-fields', $evidence['samples'][25]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.tsv', $evidence['samples'][25]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.tsv'], $evidence['samples'][25]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][25]['staticFixtureBinding']['kind']);
        $t->same('trailing-empty-fields', $evidence['samples'][25]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][25]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][25]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.tsv', $evidence['samples'][25]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-fields.native', $evidence['samples'][25]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][25]['rowCount']);
        $t->same(3, $evidence['samples'][25]['columnCount']);
        $t->same('matched', $evidence['samples'][26]['status']);
        $t->same('literal-quote-header', $evidence['samples'][26]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.tsv', $evidence['samples'][26]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.tsv'], $evidence['samples'][26]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][26]['staticFixtureBinding']['kind']);
        $t->same('literal-quote-header', $evidence['samples'][26]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][26]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][26]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.tsv', $evidence['samples'][26]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/literal-quote-header.native', $evidence['samples'][26]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][26]['rowCount']);
        $t->same(3, $evidence['samples'][26]['columnCount']);
        $t->same('matched', $evidence['samples'][27]['status']);
        $t->same('markdown-syntax-literal', $evidence['samples'][27]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.tsv', $evidence['samples'][27]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.tsv'], $evidence['samples'][27]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][27]['staticFixtureBinding']['kind']);
        $t->same('markdown-syntax-literal', $evidence['samples'][27]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][27]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][27]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.tsv', $evidence['samples'][27]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/markdown-syntax-literal.native', $evidence['samples'][27]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(3, $evidence['samples'][27]['rowCount']);
        $t->same(3, $evidence['samples'][27]['columnCount']);
        $t->same('matched', $evidence['samples'][28]['status']);
        $t->same('interior-empty-header', $evidence['samples'][28]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.tsv', $evidence['samples'][28]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.tsv'], $evidence['samples'][28]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][28]['staticFixtureBinding']['kind']);
        $t->same('interior-empty-header', $evidence['samples'][28]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][28]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][28]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.tsv', $evidence['samples'][28]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/interior-empty-header.native', $evidence['samples'][28]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][28]['rowCount']);
        $t->same(3, $evidence['samples'][28]['columnCount']);
        $t->same('matched', $evidence['samples'][29]['status']);
        $t->same('trailing-empty-header', $evidence['samples'][29]['name']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.tsv', $evidence['samples'][29]['inputPath']);
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.tsv'], $evidence['samples'][29]['readerOptions']);
        $t->same('generated-tsv-native-sample-static-fixture-binding', $evidence['samples'][29]['staticFixtureBinding']['kind']);
        $t->same('trailing-empty-header', $evidence['samples'][29]['staticFixtureBinding']['sample']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][29]['staticFixtureBinding']['inputFixture']['status']);
        $t->same('valid-static-fixture-snapshot', $evidence['samples'][29]['staticFixtureBinding']['expectedNativeFixture']['status']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.tsv', $evidence['samples'][29]['staticFixtureBinding']['inputFixture']['checkedInPath']);
        $t->same('lanes/pandoc/fixtures/generated-current-tsv-reader/trailing-empty-header.native', $evidence['samples'][29]['staticFixtureBinding']['expectedNativeFixture']['checkedInPath']);
        $t->same(4, $evidence['samples'][29]['rowCount']);
        $t->same(3, $evidence['samples'][29]['columnCount']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvNativeParity($evidence));
        $t->true(in_array('that the generated TSV samples are upstream command fixtures', $evidence['claimBoundaries']['doesNotAssert'], true));
    },
    'validates hydrated upstream delimited text reader fixture evidence' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeDelimitedTextEvidenceTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeDelimitedTextEvidenceTree($root, $repoRoot);
            $report = (new DelimitedTextUpstreamReaderEvidence($repoRoot, $root))->report();

            $t->same(DelimitedTextUpstreamReaderEvidence::STATUS_COMPLETED, $report['status']);
            $t->same('valid-upstream-delimited-text-reader-evidence', $report['validation']['status']);
            $t->same([], $report['validation']['issues']);
            $t->same(2, $report['denominator']['csvDirectFixtureCount']);
            $t->same(0, $report['denominator']['tsvDirectFixtureCount']);
            $t->same(2, $report['denominator']['csvAdjacentRstFixtureCount']);
            $t->same(0, $report['denominator']['adjacentFixtureDenominatorImpact']);
            $t->same('adjacent-rst-reader-fixtures-not-direct-delimited-text', $report['denominator']['adjacentFixtureEvidence']['relationship'] ?? null);
            $t->same('test/command/csv.md', $report['denominator']['upstreamFixtures'][0]['path']);
            $t->same('42a8bc56612d061388889a10d73b1d34fb870595785ee550ef43c6a065a77ad6', $report['denominator']['upstreamFixtures'][0]['sha256']);
            $t->same(2, $report['sourceInventory']['presentFileCount']);
            $t->same(0, $report['sourceInventory']['missingFileCount']);
            $t->same(44, $report['generatedCsvNativeParityEvidence']['generatedNativeMatchCount']);
            $t->same('generated-csv-native-parity-observed-not-upstream-fixture', $report['generatedCsvNativeParityEvidence']['parityStatus']);
            $t->same(30, $report['generatedTsvNativeParityEvidence']['generatedNativeMatchCount']);
            $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $report['generatedTsvNativeParityEvidence']['parityStatus']);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
        } finally {
            $removeTree($root);
        }
    },
    'executes generated csv and tsv pandoc executable native parity evidence' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $csv = DelimitedTextUpstreamReaderEvidence::generatedCsvPandocExecutableNativeParityEvidence($repoRoot);
        $tsv = DelimitedTextUpstreamReaderEvidence::generatedTsvPandocExecutableNativeParityEvidence($repoRoot);

        $t->same('pandoc-executable-generated-csv-native-parity-evidence', $csv['kind']);
        $t->same('generated-csv-pandoc-executable-native-parity', $csv['evidenceKind']);
        $t->same('csv', $csv['reader']);
        $t->same(2, $csv['csvDirectFixtureDenominator']);
        $t->same(44, $csv['generatedNativeCorpusSampleCount']);
        $t->same(29, $csv['sampleCount']);
        $t->same(29, $csv['comparedSampleCount']);
        $t->same(0, $csv['parseFailureCount']);
        $t->same(29, $csv['pandocExecutableNativeMatchCount']);
        $t->same(0, $csv['pandocExecutableNativeMismatchCount']);
        $t->same(100.0, $csv['pandocExecutableNativeMatchPercent']);
        $t->same(29, $csv['staticFixtureBindingValidCount']);
        $t->same(0, $csv['staticFixtureBindingInvalidCount']);
        $t->same('available', $csv['pandocExecutableStatus']);
        $t->same('pandoc 3.10', $csv['requiredPandocVersion']);
        $t->same('pandoc 3.10', $csv['pandocVersion']);
        $t->same(true, $csv['requiredPandocVersionObserved']);
        $t->same('pandoc-executable-generated-csv-native-parity-observed', $csv['parityStatus']);
        $t->same([
            'quoted-multiline',
            'post-delimiter-space',
            'quoted-linebreak',
            'trailing-empty-fields',
            'crlf-rows',
            'comment-looking-data',
            'cr-only-rows',
            'duplicate-header-labels',
            'blank-input',
            'unicode-safe',
            'quote-in-unquoted-field',
            'header-only',
            'leading-whitespace-record',
            'leading-blank-whitespace',
            'quoted-final-vtab-whitespace',
            'unquoted-final-formfeed',
            'space-only-record',
            'quoted-trailing-linebreak',
            'leading-empty-fields',
            'quoted-header-fields',
            'unquoted-tab-cell',
            'quoted-empty-fields',
            'leading-whitespace-before-quote',
            'post-delimiter-tab',
            'quoted-blank-line-cell',
            'blank-leading-header',
            'pre-delimiter-space',
            'markdown-syntax-literal',
            'formula-looking-literals',
        ], array_column($csv['samples'], 'name'));
        $t->same(array_fill(0, 29, 'matched'), array_column($csv['samples'], 'status'));
        $t->same(array_fill(0, 29, 'valid-generated-csv-native-sample-static-binding'), array_column($csv['samples'], 'staticFixtureBindingStatus'));
        $t->same([], $csv['parseFailures']);
        $t->same([], $csv['mismatches']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedCsvPandocExecutableNativeParity($csv));
        $t->true(in_array('that custom local delimiter, quote, escape, no-header, or recovery-mode samples are accepted by pandoc default CSV/TSV readers', $csv['claimBoundaries']['doesNotAssert'], true));

        $t->same('pandoc-executable-generated-tsv-native-parity-evidence', $tsv['kind']);
        $t->same('generated-tsv-pandoc-executable-native-parity', $tsv['evidenceKind']);
        $t->same('tsv', $tsv['reader']);
        $t->same(0, $tsv['tsvDirectFixtureDenominator']);
        $t->same(30, $tsv['generatedNativeCorpusSampleCount']);
        $t->same(22, $tsv['sampleCount']);
        $t->same(22, $tsv['comparedSampleCount']);
        $t->same(0, $tsv['parseFailureCount']);
        $t->same(22, $tsv['pandocExecutableNativeMatchCount']);
        $t->same(0, $tsv['pandocExecutableNativeMismatchCount']);
        $t->same(100.0, $tsv['pandocExecutableNativeMatchPercent']);
        $t->same(22, $tsv['staticFixtureBindingValidCount']);
        $t->same(0, $tsv['staticFixtureBindingInvalidCount']);
        $t->same('available', $tsv['pandocExecutableStatus']);
        $t->same('pandoc 3.10', $tsv['requiredPandocVersion']);
        $t->same('pandoc 3.10', $tsv['pandocVersion']);
        $t->same(true, $tsv['requiredPandocVersionObserved']);
        $t->same('pandoc-executable-generated-tsv-native-parity-observed', $tsv['parityStatus']);
        $t->same([
            'simple',
            'quote-trailing',
            'unicode-safe',
            'ragged-blank-fields',
            'comment-looking-data',
            'csv-quoted-literal',
            'crlf-rows',
            'blank-leading-header',
            'basic-status',
            'header-only',
            'blank-input',
            'duplicate-header-labels',
            'literal-quote-tab-split',
            'leading-blank-whitespace',
            'unquoted-final-formfeed',
            'literal-quote-newline-split',
            'leading-empty-fields',
            'trailing-empty-fields',
            'literal-quote-header',
            'markdown-syntax-literal',
            'interior-empty-header',
            'trailing-empty-header',
        ], array_column($tsv['samples'], 'name'));
        $t->same(array_fill(0, 22, 'matched'), array_column($tsv['samples'], 'status'));
        $t->same(array_fill(0, 22, 'valid-generated-tsv-native-sample-static-binding'), array_column($tsv['samples'], 'staticFixtureBindingStatus'));
        $t->same([], $tsv['parseFailures']);
        $t->same([], $tsv['mismatches']);
        $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredGeneratedTsvPandocExecutableNativeParity($tsv));
        $t->true(in_array('that custom local delimiter, quote, escape, no-header, or recovery-mode samples are accepted by pandoc default CSV/TSV readers', $tsv['claimBoundaries']['doesNotAssert'], true));
    },
    'validates supplied delimited text reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeDelimitedTextEvidenceTree, $writeRunnerTranscripts): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeDelimitedTextEvidenceTree($root, $repoRoot);
            $baseReport = (new DelimitedTextUpstreamReaderEvidence($root, '.'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = ['Command: csv.md #1'];
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc command reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $artifactPath = $root . '/result.json';
            $report = (new DelimitedTextUpstreamReaderEvidence($root, '.', $artifactPath))->report();
            $text = DelimitedTextUpstreamReaderEvidence::formatTextReport($report);

            $t->same('completed', $report['runnerEvidence']['status']);
            $t->same(true, $report['runnerEvidence']['executed']);
            $t->same('runner-result-artifact-validated', $report['runnerEvidence']['commandPlanStatus']);
            $t->same('valid-upstream-delimited-text-reader-runner-result-artifact', $report['runnerEvidence']['validation']['status']);
            $t->same([], $report['runnerEvidence']['validation']['issues']);
            $t->same('upstream-delimited-text-reader-runner-result-artifact', $report['runnerEvidence']['resultArtifact']['kind']);
            $t->same(true, $report['runnerEvidence']['resultArtifact']['present']);
            $t->same(hash_file('sha256', $artifactPath), $report['runnerEvidence']['resultArtifact']['sha256']);
            $t->same(filesize($artifactPath), $report['runnerEvidence']['resultArtifact']['bytes']);
            $t->same(DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT, $report['runnerEvidence']['upstreamBinding']['observedCommit']);
            $t->same($runnerPlan['target'], $report['runnerEvidence']['target']);
            $t->same($runnerPlan['futureCommands'][2], $report['runnerEvidence']['command']);
            $t->same($testNames, $report['runnerEvidence']['observed']['testNames']);
            $t->same($runnerPlan['requiredTranscripts'], $report['runnerEvidence']['observed']['transcriptPaths']);
            $t->same($transcripts, $report['runnerEvidence']['observed']['transcripts']);
            $t->same($transcripts, $report['runnerEvidence']['expected']['transcripts']);
            $t->same('upstream-delimited-text-reader-runner-transcript', $report['runnerEvidence']['transcripts'][0]['kind']);
            $t->same(true, $report['runnerEvidence']['transcripts'][0]['present']);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($report));
            $t->same(false, DelimitedTextUpstreamReaderEvidence::hasRunnerPlanEvidence($report));
            $t->contains('Runner status: completed', $text);
            $t->contains('Runner plan: runner-result-artifact-validated', $text);
            $t->contains('Runner result artifact: valid-upstream-delimited-text-reader-runner-result-artifact', $text);
            $t->contains('Supplied upstream Haskell/Cabal runner result artifact is validated', $text);

            $payload = $validPayload;
            $payload['failedCount'] = 1;
            $payload['exitCode'] = 1;
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badReport = (new DelimitedTextUpstreamReaderEvidence($root, '.', $root . '/bad-result.json'))->report();

            $t->same('invalid', $badReport['runnerEvidence']['status']);
            $t->same('invalid-upstream-delimited-text-reader-runner-result-artifact', $badReport['runnerEvidence']['validation']['status']);
            $t->true(in_array('runner-result-exit-code-nonzero', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->true(in_array('runner-result-counts-mismatch', $badReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, DelimitedTextUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badReport));

            $badTranscriptPayload = $validPayload;
            $badTranscriptPayload['transcripts'][0]['bytes'] = 0;
            $writeFile($root, 'bad-transcript-result.json', json_encode($badTranscriptPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $badTranscriptReport = (new DelimitedTextUpstreamReaderEvidence($root, '.', $root . '/bad-transcript-result.json'))->report();

            $t->same('invalid', $badTranscriptReport['runnerEvidence']['status']);
            $t->true(in_array('runner-result-transcript-bytes-mismatch', $badTranscriptReport['runnerEvidence']['validation']['issues'], true));
            $t->same(false, DelimitedTextUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($badTranscriptReport));
        } finally {
            $removeTree($root);
        }
    },
    'cli reports generated tsv native parity without changing tsv denominator' => static function (TestRunner $t): void {
        $repoRoot = dirname(__DIR__, 3);
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg($repoRoot . '/tools/pandoc-delimited-text-reader-evidence.php')
            . ' --repo-root=' . escapeshellarg($repoRoot)
            . ' --json'
            . ' --require-honest-denominators'
            . ' --require-generated-csv-native-parity=44'
            . ' --require-generated-tsv-native-parity=30'
            . ' --require-pandoc-executable-csv-native-parity=29'
            . ' --require-pandoc-executable-tsv-native-parity=22'
            . ' --require-runner-not-run'
            . ' --require-runner-plan'
            . ' --require-no-validation-issues';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(0, $decoded['tsv']['denominator']);
        $t->same(0, $decoded['tsv']['tsvDirectFixtureDenominator']);
        $t->same(44, $decoded['generatedCsvNativeParity']['sampleCount']);
        $t->same(44, $decoded['generatedCsvNativeParity']['generatedNativeMatchCount']);
        $t->same('generated-csv-native-parity-observed-not-upstream-fixture', $decoded['generatedCsvNativeParity']['parityStatus']);
        $t->same(2, $decoded['csv']['adjacentFixtureEvidence']['fixtureCount']);
        $t->same(0, $decoded['csv']['adjacentFixtureEvidence']['csvDirectFixtureDenominatorImpact']);
        $t->same('rst', $decoded['csv']['adjacentFixtureEvidence']['reader']);
        $t->same(30, $decoded['tsv']['generatedNativeParitySampleCount']);
        $t->same(30, $decoded['generatedTsvNativeParity']['generatedNativeMatchCount']);
        $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $decoded['generatedTsvNativeParity']['parityStatus']);
        $t->same(29, $decoded['generatedCsvPandocExecutableNativeParity']['sampleCount']);
        $t->same(29, $decoded['generatedCsvPandocExecutableNativeParity']['pandocExecutableNativeMatchCount']);
        $t->same('pandoc-executable-generated-csv-native-parity-observed', $decoded['generatedCsvPandocExecutableNativeParity']['parityStatus']);
        $t->same(22, $decoded['generatedTsvPandocExecutableNativeParity']['sampleCount']);
        $t->same(22, $decoded['generatedTsvPandocExecutableNativeParity']['pandocExecutableNativeMatchCount']);
        $t->same('pandoc-executable-generated-tsv-native-parity-observed', $decoded['generatedTsvPandocExecutableNativeParity']['parityStatus']);
        $t->same(true, $decoded['validation']['generatedCsvPandocExecutableNativeParity']);
        $t->same(true, $decoded['validation']['generatedTsvPandocExecutableNativeParity']);
        $t->same(true, $decoded['validation']['runnerPlan']);
        $t->same('planned-not-run', $decoded['csv']['runnerEvidence']['commandPlanStatus']);
        $t->same('upstream-runner-command-plan', $decoded['csv']['runnerEvidence']['commandPlan']['kind']);
        $t->same('hydrated Pandoc upstream checkout root', $decoded['csv']['runnerEvidence']['commandPlan']['workingDirectory']);
        $t->same('offline', $decoded['csv']['runnerEvidence']['commandPlan']['networkMode']);
        $t->same(3, $decoded['csv']['runnerEvidence']['commandPlan']['commandCount']);
        $t->same('upstream-runner-non-execution-boundary', $decoded['csv']['runnerEvidence']['executionBoundary']['kind']);
        $t->same('plan-only-not-run', $decoded['csv']['runnerEvidence']['executionBoundary']['status']);
        $t->same(true, $decoded['csv']['runnerEvidence']['executionBoundary']['planOnly']);
        $t->same(0, $decoded['csv']['runnerEvidence']['executionBoundary']['executedCommandCount']);
        $t->same(false, $decoded['csv']['runnerEvidence']['executionBoundary']['upstreamRunnerParityClaimed']);
        $t->same(['Command:', 'csv.md', '#1'], $decoded['csv']['runnerEvidence']['target']['tastyGroupPath']);
        $t->same('$2 == "Command:" && $3 == "csv.md" && $4 == "#1"', $decoded['csv']['runnerEvidence']['target']['tastyPattern']);
        $t->same(false, $decoded['tsv']['runnerEvidence']['target']['tsvDirectFixtureAvailable']);
        $t->same('plan-only-not-run', $decoded['tsv']['runnerEvidence']['executionBoundary']['status']);
        $t->same([], $decoded['validationIssues']);
    },
    'cli gates supplied delimited text reader upstream runner result artifact' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFile, $writeDelimitedTextEvidenceTree, $writeRunnerTranscripts): void {
        $repoRoot = dirname(__DIR__, 3);
        $root = $makeTempDir();
        try {
            $writeDelimitedTextEvidenceTree($root, $repoRoot);
            $baseReport = (new DelimitedTextUpstreamReaderEvidence($root, '.'))->report();
            $runnerPlan = $baseReport['runnerEvidence'];
            $transcripts = $writeRunnerTranscripts($root, $runnerPlan['requiredTranscripts']);
            $testNames = ['Command: csv.md #1'];
            $payload = [
                'schemaVersion' => 2,
                'runner' => 'Cabal/Tasty Pandoc command reader suite',
                'runnerExecuted' => true,
                'upstream' => [
                    'name' => 'jgm/pandoc',
                    'commit' => DelimitedTextUpstreamReaderEvidence::EXPECTED_UPSTREAM_COMMIT,
                ],
                'target' => $runnerPlan['target'],
                'command' => $runnerPlan['futureCommands'][2],
                'exitCode' => 0,
                'testCount' => count($testNames),
                'passedCount' => count($testNames),
                'failedCount' => 0,
                'skippedCount' => 0,
                'testNames' => $testNames,
                'transcriptPaths' => $runnerPlan['requiredTranscripts'],
                'transcripts' => $transcripts,
            ];
            $validPayload = $payload;
            $writeFile($root, 'result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-delimited-text-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($root)
                . ' --runner-result-artifact=' . escapeshellarg($root . '/result.json')
                . ' --json'
                . ' --require-runner-result-artifact';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(true, $decoded['validation']['runnerResultArtifact']);
            $t->same('completed', $decoded['runnerResultArtifactEvidence']['status']);
            $t->same('valid-upstream-delimited-text-reader-runner-result-artifact', $decoded['runnerResultArtifactEvidence']['validation']['status']);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRunnerResultArtifactEvidence($decoded['runnerResultArtifactEvidence']));

            $payload = $validPayload;
            $payload['target']['tastyPattern'] = '$2 == "Readers" && $3 == "Markdown"';
            $writeFile($root, 'bad-result.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $failingCommand = str_replace('result.json', 'bad-result.json', $command) . ' 2>/dev/null';
            $failingOutput = [];
            $failingExitCode = 0;
            exec($failingCommand, $failingOutput, $failingExitCode);

            $t->same(1, $failingExitCode);
        } finally {
            $removeTree($root);
        }
    },
    'cli gates generated csv native parity against explicit repo root' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $missingRoot = $makeTempDir();
        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-delimited-text-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($missingRoot)
                . ' --json'
                . ' --require-generated-csv-native-parity=44'
                . ' 2>/dev/null';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same(44, $decoded['generatedCsvNativeParity']['sampleCount']);
            $t->same(0, $decoded['generatedCsvNativeParity']['comparedSampleCount']);
            $t->same(44, $decoded['generatedCsvNativeParity']['parseFailureCount']);
            $t->same(0, $decoded['generatedCsvNativeParity']['staticFixtureBindingValidCount']);
            $t->same(44, $decoded['generatedCsvNativeParity']['staticFixtureBindingInvalidCount']);
            $t->same('blocked-by-generated-csv-native-fixture-validation', $decoded['generatedCsvNativeParity']['parityStatus']);
            $t->same(array_fill(0, 44, 'invalid-generated-csv-native-sample-static-binding'), array_column($decoded['generatedCsvNativeParity']['samples'], 'staticFixtureBindingStatus'));
            $t->true(in_array('Generated CSV native parity parse failure count must be 0', $decoded['validationIssues'], true));
        } finally {
            $removeTree($missingRoot);
        }
    },
    'cli gates generated tsv native parity against explicit repo root' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $repoRoot = dirname(__DIR__, 3);
        $missingRoot = $makeTempDir();
        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg($repoRoot . '/tools/pandoc-delimited-text-reader-evidence.php')
                . ' --repo-root=' . escapeshellarg($missingRoot)
                . ' --json'
                . ' --require-generated-tsv-native-parity=30'
                . ' 2>/dev/null';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same(30, $decoded['generatedTsvNativeParity']['sampleCount']);
            $t->same(0, $decoded['generatedTsvNativeParity']['comparedSampleCount']);
            $t->same(30, $decoded['generatedTsvNativeParity']['parseFailureCount']);
            $t->same(0, $decoded['generatedTsvNativeParity']['staticFixtureBindingValidCount']);
            $t->same(30, $decoded['generatedTsvNativeParity']['staticFixtureBindingInvalidCount']);
            $t->same('blocked-by-generated-tsv-native-fixture-validation', $decoded['generatedTsvNativeParity']['parityStatus']);
            $t->same(array_fill(0, 30, 'invalid-generated-tsv-native-sample-static-binding'), array_column($decoded['generatedTsvNativeParity']['samples'], 'staticFixtureBindingStatus'));
            $t->true(in_array('Generated TSV native parity parse failure count must be 0', $decoded['validationIssues'], true));
        } finally {
            $removeTree($missingRoot);
        }
    },
];
