<?php

declare(strict_types=1);

use PortLibs\Pandoc\HtmlNativeAstComparisonHarness;

$fixtureRoot = static fn (): string => dirname(__DIR__) . '/fixtures';

return [
    'skips html native ast comparison when source directory is absent' => static function (TestRunner $t): void {
        $missing = sys_get_temp_dir() . '/missing-html-native-' . bin2hex(random_bytes(4));
        $report = (new HtmlNativeAstComparisonHarness())->run($missing);
        $text = (new HtmlNativeAstComparisonHarness())->formatReport($report);

        $t->same('skipped', $report['status']);
        $t->same(true, $report['skipped']);
        $t->same('html-native-fixture-directory-missing', $report['reason']);
        $t->same(0, $report['comparedPairCount']);
        $t->same('not-evaluated-source-directory-unavailable', $report['astParityStatus']);
        $t->contains('Pandoc HTML/native AST comparison: skipped', $text);
    },

    'checked-in html fixtures match native ast shape' => static function (TestRunner $t) use ($fixtureRoot): void {
        $root = $fixtureRoot();
        $rowHeaderHtml = $root . '/upstream-native-html-row-header-table.html';
        $rowHeaderNative = $root . '/upstream-native-html-row-header-table.native';
        $noterefHtml = $root . '/upstream-html-doc-noteref-footnotes.html';
        $noterefNative = $root . '/upstream-html-doc-noteref-footnotes.native';
        $tablePlacementHtml = $root . '/upstream-html-doc-noteref-table-placement.html';
        $tablePlacementNative = $root . '/upstream-html-doc-noteref-table-placement.native';
        $lineBlockHtml = $root . '/upstream-html-line-block.html';
        $lineBlockNative = $root . '/upstream-html-line-block.native';
        $styleRawHtml = $root . '/upstream-html-style-raw-block.html';
        $styleRawNative = $root . '/upstream-html-style-raw-block.native';

        $t->true(is_file($rowHeaderHtml), 'HTML row-header fixture must be checked in');
        $t->true(is_file($rowHeaderNative), 'Native row-header fixture must be checked in');
        $t->true(is_file($noterefHtml), 'HTML doc-noteref fixture must be checked in');
        $t->true(is_file($noterefNative), 'Native doc-noteref fixture must be checked in');
        $t->true(is_file($tablePlacementHtml), 'HTML doc-noteref table placement fixture must be checked in');
        $t->true(is_file($tablePlacementNative), 'Native doc-noteref table placement fixture must be checked in');
        $t->true(is_file($lineBlockHtml), 'HTML line-block fixture must be checked in');
        $t->true(is_file($lineBlockNative), 'Native line-block fixture must be checked in');
        $t->true(is_file($styleRawHtml), 'HTML style raw-block fixture must be checked in');
        $t->true(is_file($styleRawNative), 'Native style raw-block fixture must be checked in');

        $harness = new HtmlNativeAstComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(5, $report['totalPairCount']);
        $t->same(5, $report['comparedPairCount']);
        $t->same(5, $report['htmlParsedCount']);
        $t->same(5, $report['nativeParsedCount']);
        $t->same(5, $report['bothParsedCount']);
        $t->same(0, $report['parseFailureCount']);
        $t->same(5, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same('normalized-ast-equality-observed-not-runner-parity', $report['astParityStatus']);
        $t->same(true, HtmlNativeAstComparisonHarness::hasRequiredMappedParity($report, 5));
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same('The current checked-in gate covers 5 paired fixture(s).', $report['orderedRemainingGaps'][2]['currentEvidence']);
        $t->contains('pairs: total=5 compared=5 parsedBoth=5 parseFailures=0', $text);
        $t->contains('normalizedAst: matches=5 (100.00%) mismatches=0', $text);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-html-native-ast.php')
            . ' --html-dir=' . escapeshellarg($root)
            . ' --json'
            . ' summary'
            . ' --require-mapped-parity=5';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(5, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
    },
];
