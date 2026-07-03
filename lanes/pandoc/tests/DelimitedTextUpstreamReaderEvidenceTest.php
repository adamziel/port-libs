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
        $t->same(false, DelimitedTextUpstreamReaderEvidence::hasNoValidationIssues($report));
        $t->contains('Pandoc delimited text reader evidence', $text);
        $t->contains('Static current evidence: valid-checked-in-current-delimited-text-reader-evidence checkedInFixtures=2', $text);
        $t->contains('Generated CSV native parity: 14/14 status=generated-csv-native-parity-observed-not-upstream-fixture', $text);
        $t->contains('Generated TSV native parity: 9/9 status=generated-tsv-native-parity-observed-not-upstream-fixture', $text);
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
        $t->same(14, $evidence['generatedCsvNativeStaticEvidence']['sampleCount']);
        $t->same(28, $evidence['generatedCsvNativeStaticEvidence']['checkedInFixtureCount']);
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
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][6]['readerOptions']);
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
        $t->same([], $evidence['generatedCsvNativeStaticEvidence']['samples'][9]['readerOptions']);
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
        $t->same('static-checked-in-generated-tsv-native-parity-fixture-evidence', $evidence['generatedTsvNativeStaticEvidence']['kind']);
        $t->same(9, $evidence['generatedTsvNativeStaticEvidence']['sampleCount']);
        $t->same(18, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtureCount']);
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
        $t->same('a6f8a232c40e26e421c2640f35ff1f1010f24eb7e42341b9b09dfadfb86a2bee', $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][7]['checkedInFile']['sha256']);
        $t->same(2159, $evidence['generatedTsvNativeStaticEvidence']['checkedInFixtures'][7]['checkedInFile']['bytes']);
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
        $t->same(14, $evidence['sampleCount']);
        $t->same(14, $evidence['comparedSampleCount']);
        $t->same(0, $evidence['parseFailureCount']);
        $t->same(14, $evidence['generatedNativeMatchCount']);
        $t->same(0, $evidence['generatedNativeMismatchCount']);
        $t->same(100.0, $evidence['generatedNativeMatchPercent']);
        $t->same(14, $evidence['staticFixtureBindingValidCount']);
        $t->same(0, $evidence['staticFixtureBindingInvalidCount']);
        $t->same(array_fill(0, 14, 'valid-generated-csv-native-sample-static-binding'), array_column($evidence['samples'], 'staticFixtureBindingStatus'));
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
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/text-after-closing-quote.csv'], $evidence['samples'][6]['readerOptions']);
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
        $t->same(['sourcePath' => 'lanes/pandoc/fixtures/generated-current-csv-reader/unquoted-space-empty-quoted.csv'], $evidence['samples'][9]['readerOptions']);
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
        $t->same(9, $evidence['sampleCount']);
        $t->same(9, $evidence['comparedSampleCount']);
        $t->same(0, $evidence['parseFailureCount']);
        $t->same(9, $evidence['generatedNativeMatchCount']);
        $t->same(0, $evidence['generatedNativeMismatchCount']);
        $t->same(100.0, $evidence['generatedNativeMatchPercent']);
        $t->same(9, $evidence['staticFixtureBindingValidCount']);
        $t->same(0, $evidence['staticFixtureBindingInvalidCount']);
        $t->same(array_fill(0, 9, 'valid-generated-tsv-native-sample-static-binding'), array_column($evidence['samples'], 'staticFixtureBindingStatus'));
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
        $t->same(4, $evidence['samples'][3]['columnCount']);
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
            $t->same(14, $report['generatedCsvNativeParityEvidence']['generatedNativeMatchCount']);
            $t->same('generated-csv-native-parity-observed-not-upstream-fixture', $report['generatedCsvNativeParityEvidence']['parityStatus']);
            $t->same(9, $report['generatedTsvNativeParityEvidence']['generatedNativeMatchCount']);
            $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $report['generatedTsvNativeParityEvidence']['parityStatus']);
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasNoValidationIssues($report));
            $t->same(true, DelimitedTextUpstreamReaderEvidence::hasRequiredStaticCurrentEvidence($report));
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
            . ' --require-generated-csv-native-parity'
            . ' --require-generated-tsv-native-parity'
            . ' --require-runner-not-run'
            . ' --require-no-validation-issues';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(0, $decoded['tsv']['denominator']);
        $t->same(0, $decoded['tsv']['tsvDirectFixtureDenominator']);
        $t->same(14, $decoded['generatedCsvNativeParity']['sampleCount']);
        $t->same(14, $decoded['generatedCsvNativeParity']['generatedNativeMatchCount']);
        $t->same('generated-csv-native-parity-observed-not-upstream-fixture', $decoded['generatedCsvNativeParity']['parityStatus']);
        $t->same(2, $decoded['csv']['adjacentFixtureEvidence']['fixtureCount']);
        $t->same(0, $decoded['csv']['adjacentFixtureEvidence']['csvDirectFixtureDenominatorImpact']);
        $t->same('rst', $decoded['csv']['adjacentFixtureEvidence']['reader']);
        $t->same(9, $decoded['tsv']['generatedNativeParitySampleCount']);
        $t->same(9, $decoded['generatedTsvNativeParity']['generatedNativeMatchCount']);
        $t->same('generated-tsv-native-parity-observed-not-upstream-fixture', $decoded['generatedTsvNativeParity']['parityStatus']);
        $t->same([], $decoded['validationIssues']);
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
                . ' --require-generated-csv-native-parity'
                . ' 2>/dev/null';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same(14, $decoded['generatedCsvNativeParity']['sampleCount']);
            $t->same(0, $decoded['generatedCsvNativeParity']['comparedSampleCount']);
            $t->same(14, $decoded['generatedCsvNativeParity']['parseFailureCount']);
            $t->same(0, $decoded['generatedCsvNativeParity']['staticFixtureBindingValidCount']);
            $t->same(14, $decoded['generatedCsvNativeParity']['staticFixtureBindingInvalidCount']);
            $t->same('blocked-by-generated-csv-native-fixture-validation', $decoded['generatedCsvNativeParity']['parityStatus']);
            $t->same(array_fill(0, 14, 'invalid-generated-csv-native-sample-static-binding'), array_column($decoded['generatedCsvNativeParity']['samples'], 'staticFixtureBindingStatus'));
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
                . ' --require-generated-tsv-native-parity'
                . ' 2>/dev/null';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same(9, $decoded['generatedTsvNativeParity']['sampleCount']);
            $t->same(0, $decoded['generatedTsvNativeParity']['comparedSampleCount']);
            $t->same(9, $decoded['generatedTsvNativeParity']['parseFailureCount']);
            $t->same(0, $decoded['generatedTsvNativeParity']['staticFixtureBindingValidCount']);
            $t->same(9, $decoded['generatedTsvNativeParity']['staticFixtureBindingInvalidCount']);
            $t->same('blocked-by-generated-tsv-native-fixture-validation', $decoded['generatedTsvNativeParity']['parityStatus']);
            $t->same(array_fill(0, 9, 'invalid-generated-tsv-native-sample-static-binding'), array_column($decoded['generatedTsvNativeParity']['samples'], 'staticFixtureBindingStatus'));
            $t->true(in_array('Generated TSV native parity parse failure count must be 0', $decoded['validationIssues'], true));
        } finally {
            $removeTree($missingRoot);
        }
    },
];
