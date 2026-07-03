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
        $anchorImageHtml = $root . '/upstream-html-anchor-image-attrs.html';
        $anchorImageNative = $root . '/upstream-html-anchor-image-attrs.native';
        $baseMediaHtml = $root . '/upstream-html-base-media.html';
        $baseMediaNative = $root . '/upstream-html-base-media.native';
        $bdoDirectionHtml = $root . '/upstream-html-bdo-direction.html';
        $bdoDirectionNative = $root . '/upstream-html-bdo-direction.native';
        $rowHeaderHtml = $root . '/upstream-native-html-row-header-table.html';
        $rowHeaderNative = $root . '/upstream-native-html-row-header-table.native';
        $noterefHtml = $root . '/upstream-html-doc-noteref-footnotes.html';
        $noterefNative = $root . '/upstream-html-doc-noteref-footnotes.native';
        $tablePlacementHtml = $root . '/upstream-html-doc-noteref-table-placement.html';
        $tablePlacementNative = $root . '/upstream-html-doc-noteref-table-placement.native';
        $lineBlockHtml = $root . '/upstream-html-line-block.html';
        $lineBlockNative = $root . '/upstream-html-line-block.native';
        $preCodeHtml = $root . '/upstream-html-pre-code-attributes.html';
        $preCodeNative = $root . '/upstream-html-pre-code-attributes.native';
        $preCodeBreakHtml = $root . '/upstream-html-pre-code-br.html';
        $preCodeBreakNative = $root . '/upstream-html-pre-code-br.native';
        $inlineCodeHtml = $root . '/upstream-html-inline-code-aliases.html';
        $inlineCodeNative = $root . '/upstream-html-inline-code-aliases.native';
        $langMetadataHtml = $root . '/upstream-html-lang-metadata.html';
        $langMetadataNative = $root . '/upstream-html-lang-metadata.native';
        $headerNativeDivsHtml = $root . '/upstream-html-header-native-divs.html';
        $headerNativeDivsNative = $root . '/upstream-html-header-native-divs.native';
        $mainNativeDivsHtml = $root . '/upstream-html-main-native-divs.html';
        $mainNativeDivsNative = $root . '/upstream-html-main-native-divs.native';
        $sectionAsideNativeDivsHtml = $root . '/upstream-html-section-aside-native-divs.html';
        $sectionAsideNativeDivsNative = $root . '/upstream-html-section-aside-native-divs.native';
        $spanStrikeoutHtml = $root . '/upstream-html-span-strikeout.html';
        $spanStrikeoutNative = $root . '/upstream-html-span-strikeout.native';
        $spanlikeHtml = $root . '/upstream-html-spanlike-inline.html';
        $spanlikeNative = $root . '/upstream-html-spanlike-inline.native';
        $styleRawHtml = $root . '/upstream-html-style-raw-block.html';
        $styleRawNative = $root . '/upstream-html-style-raw-block.native';
        $scriptRawHtml = $root . '/upstream-html-script-raw-block.html';
        $scriptRawNative = $root . '/upstream-html-script-raw-block.native';
        $smallInlineHtml = $root . '/upstream-html-small-inline.html';
        $smallInlineNative = $root . '/upstream-html-small-inline.native';
        $standaloneLinebreakHtml = $root . '/upstream-html-standalone-linebreak.html';
        $standaloneLinebreakNative = $root . '/upstream-html-standalone-linebreak.native';

        $t->true(is_file($anchorImageHtml), 'HTML anchor/image fixture must be checked in');
        $t->true(is_file($anchorImageNative), 'Native anchor/image fixture must be checked in');
        $t->true(is_file($baseMediaHtml), 'HTML base-media fixture must be checked in');
        $t->true(is_file($baseMediaNative), 'Native base-media fixture must be checked in');
        $t->true(is_file($bdoDirectionHtml), 'HTML bdo-direction fixture must be checked in');
        $t->true(is_file($bdoDirectionNative), 'Native bdo-direction fixture must be checked in');
        $t->true(is_file($rowHeaderHtml), 'HTML row-header fixture must be checked in');
        $t->true(is_file($rowHeaderNative), 'Native row-header fixture must be checked in');
        $t->true(is_file($noterefHtml), 'HTML doc-noteref fixture must be checked in');
        $t->true(is_file($noterefNative), 'Native doc-noteref fixture must be checked in');
        $t->true(is_file($tablePlacementHtml), 'HTML doc-noteref table placement fixture must be checked in');
        $t->true(is_file($tablePlacementNative), 'Native doc-noteref table placement fixture must be checked in');
        $t->true(is_file($lineBlockHtml), 'HTML line-block fixture must be checked in');
        $t->true(is_file($lineBlockNative), 'Native line-block fixture must be checked in');
        $t->true(is_file($preCodeHtml), 'HTML pre-code attributes fixture must be checked in');
        $t->true(is_file($preCodeNative), 'Native pre-code attributes fixture must be checked in');
        $t->true(is_file($preCodeBreakHtml), 'HTML pre-code break fixture must be checked in');
        $t->true(is_file($preCodeBreakNative), 'Native pre-code break fixture must be checked in');
        $t->true(is_file($inlineCodeHtml), 'HTML inline-code fixture must be checked in');
        $t->true(is_file($inlineCodeNative), 'Native inline-code fixture must be checked in');
        $t->true(is_file($langMetadataHtml), 'HTML lang-metadata fixture must be checked in');
        $t->true(is_file($langMetadataNative), 'Native lang-metadata fixture must be checked in');
        $t->true(is_file($headerNativeDivsHtml), 'HTML header native-divs fixture must be checked in');
        $t->true(is_file($headerNativeDivsNative), 'Native header native-divs fixture must be checked in');
        $t->true(is_file($mainNativeDivsHtml), 'HTML main native-divs fixture must be checked in');
        $t->true(is_file($mainNativeDivsNative), 'Native main native-divs fixture must be checked in');
        $t->true(is_file($sectionAsideNativeDivsHtml), 'HTML section-aside native-divs fixture must be checked in');
        $t->true(is_file($sectionAsideNativeDivsNative), 'Native section-aside native-divs fixture must be checked in');
        $t->true(is_file($spanStrikeoutHtml), 'HTML span-strikeout fixture must be checked in');
        $t->true(is_file($spanStrikeoutNative), 'Native span-strikeout fixture must be checked in');
        $t->true(is_file($spanlikeHtml), 'HTML spanlike-inline fixture must be checked in');
        $t->true(is_file($spanlikeNative), 'Native spanlike-inline fixture must be checked in');
        $t->true(is_file($styleRawHtml), 'HTML style raw-block fixture must be checked in');
        $t->true(is_file($styleRawNative), 'Native style raw-block fixture must be checked in');
        $t->true(is_file($scriptRawHtml), 'HTML script raw-block fixture must be checked in');
        $t->true(is_file($scriptRawNative), 'Native script raw-block fixture must be checked in');
        $t->true(is_file($smallInlineHtml), 'HTML small-inline fixture must be checked in');
        $t->true(is_file($smallInlineNative), 'Native small-inline fixture must be checked in');
        $t->true(is_file($standaloneLinebreakHtml), 'HTML standalone linebreak fixture must be checked in');
        $t->true(is_file($standaloneLinebreakNative), 'Native standalone linebreak fixture must be checked in');

        $harness = new HtmlNativeAstComparisonHarness();
        $report = $harness->run($root);
        $text = $harness->formatReport($report);

        $t->same('completed', $report['status']);
        $t->same(20, $report['totalPairCount']);
        $t->same(20, $report['comparedPairCount']);
        $t->same(20, $report['htmlParsedCount']);
        $t->same(20, $report['nativeParsedCount']);
        $t->same(20, $report['bothParsedCount']);
        $t->same(0, $report['parseFailureCount']);
        $t->same(20, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same('normalized-ast-equality-observed-not-runner-parity', $report['astParityStatus']);
        $t->same(true, HtmlNativeAstComparisonHarness::hasRequiredMappedParity($report, 20));
        $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
        $t->same('The current checked-in gate covers 20 paired fixture(s).', $report['orderedRemainingGaps'][2]['currentEvidence']);
        $t->contains('pairs: total=20 compared=20 parsedBoth=20 parseFailures=0', $text);
        $t->contains('normalizedAst: matches=20 (100.00%) mismatches=0', $text);

        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-html-native-ast.php')
            . ' --html-dir=' . escapeshellarg($root)
            . ' --json'
            . ' summary'
            . ' --require-mapped-parity=20';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(20, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
    },
];
