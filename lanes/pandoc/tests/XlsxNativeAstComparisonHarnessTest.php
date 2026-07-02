<?php

declare(strict_types=1);

use PortLibs\Pandoc\AstNode;
use PortLibs\Pandoc\XlsxNativeAstComparisonHarness;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-native-ast-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary XLSX AST directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary XLSX AST directory {$base}");
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

$fixtureDir = static fn (): string => dirname(__DIR__) . '/fixtures/upstream-current-xlsx-reader';

$copyBasicFixture = static function (string $root, string $stem = 'basic') use ($fixtureDir): void {
    copy($fixtureDir() . '/basic.xlsx', $root . '/' . $stem . '.xlsx');
    copy($fixtureDir() . '/basic.native', $root . '/' . $stem . '.native');
};

return [
    'skips xlsx native ast comparison when cache is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        $missing = $root . '/missing/test/xlsx-reader';

        try {
            $harness = new XlsxNativeAstComparisonHarness();
            $report = $harness->run($missing);
            $text = $harness->formatReport($report);

            $t->same(1, $report['schemaVersion']);
            $t->same('pandoc-xlsx-native-ast', $report['tool']);
            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('upstream-cache-missing', $report['reason']);
            $t->same('normalized-ast-comparison-not-full-xlsx-parity', $report['verdict']);
            $t->same('xlsx-native-normalized-ast-comparison', $report['evidenceKind']);
            $t->same(0, $report['comparedPairCount']);
            $t->same(0, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same('not-evaluated-source-directory-unavailable', $report['astParityStatus']);
            $t->same('normalized-xlsx-native-ast-equality', $report['orderedRemainingGaps'][0]['id']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc XLSX/native AST comparison: skipped', $text);
            $t->contains('orderedRemainingGaps:', $text);
        } finally {
            $removeTree($root);
        }
    },

    'reports xlsx normalized ast matches and mismatches without claiming full parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture): void {
        $root = $makeTempDir();

        try {
            $copyBasicFixture($root, 'different');
            $copyBasicFixture($root, 'same');
            $native = (string) file_get_contents($root . '/different.native');
            file_put_contents($root . '/different.native', str_replace('Str "Anton"', 'Str "Antonia"', $native));

            $harness = new XlsxNativeAstComparisonHarness();
            $report = $harness->run($root);
            $text = $harness->formatReport($report);
            $categoryNames = array_map(
                static fn (array $category): string => (string) $category['category'],
                $report['mismatchCategories']
            );

            $t->same('completed', $report['status']);
            $t->same(false, $report['skipped']);
            $t->same(2, $report['totalPairCount']);
            $t->same(2, $report['comparedPairCount']);
            $t->same(2, $report['bothParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(1, $report['normalizedAstMismatchCount']);
            $t->same(50.0, $report['normalizedAstMatchPercent']);
            $t->same('normalized-ast-mismatches-observed', $report['astParityStatus']);
            $t->same('different', $report['mismatchComparisons'][0]['fixture']);
            $t->contains(' value ', $report['mismatchComparisons'][0]['firstDifference']);
            $t->true(in_array('scalar-value', $categoryNames, true));
            $t->same('open', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('normalizedAst: matches=1 (50.00%) mismatches=1 status=normalized-ast-mismatches-observed', $text);
            $t->contains('mismatchExamples:', $text);
            $t->contains('1. normalized-xlsx-native-ast-equality [open]', $text);
        } finally {
            $removeTree($root);
        }
    },

    'reports xlsx normalized ast equality separately from upstream runner parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture): void {
        $root = $makeTempDir();

        try {
            $copyBasicFixture($root);

            $report = (new XlsxNativeAstComparisonHarness())->run($root);

            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same('normalized-ast-equality-observed-not-runner-parity', $report['astParityStatus']);
            $t->same('covered-by-current-normalized-ast-evidence', $report['orderedRemainingGaps'][0]['status']);
            $t->same('upstream-xlsx-reader-runner-results', $report['orderedRemainingGaps'][1]['id']);
            $t->same('open', $report['orderedRemainingGaps'][1]['status']);
            $t->same('upstream-xlsx-fixture-corpus-coverage', $report['orderedRemainingGaps'][2]['id']);
            $t->same('open', $report['orderedRemainingGaps'][2]['status']);
            $t->true(in_array('local XLSX package review attrs', $report['normalizationPolicy']['excludes'], true));
            $t->true(in_array('reader-specific adjacent Str/Space text-node segmentation', $report['normalizationPolicy']['excludes'], true));
        } finally {
            $removeTree($root);
        }
    },

    'matches checked-in current upstream xlsx reader golden pair through normalized ast harness' => static function (TestRunner $t) use ($fixtureDir): void {
        $report = (new XlsxNativeAstComparisonHarness())->run($fixtureDir());

        $t->same('completed', $report['status']);
        $t->same(1, $report['totalPairCount']);
        $t->same(1, $report['comparedPairCount']);
        $t->same(1, $report['xlsxParsedCount']);
        $t->same(1, $report['nativeParsedCount']);
        $t->same(1, $report['bothParsedCount']);
        $t->same(0, $report['parseFailureCount']);
        $t->same(1, $report['normalizedAstMatchCount']);
        $t->same(0, $report['normalizedAstMismatchCount']);
        $t->same(true, XlsxNativeAstComparisonHarness::hasRequiredMappedParity($report, 1));
    },

    'cli gates required mapped xlsx parity from checked-in fixture' => static function (TestRunner $t) use ($fixtureDir): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-xlsx-native-ast.php')
            . ' --upstream-xlsx-dir='
            . escapeshellarg($fixtureDir())
            . ' --json'
            . ' summary'
            . ' --require-mapped-parity=1';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same('completed', $decoded['status']);
        $t->same(1, $decoded['normalizedAstMatchCount']);
        $t->same(0, $decoded['normalizedAstMismatchCount']);
        $t->same(true, XlsxNativeAstComparisonHarness::hasRequiredMappedParity($decoded, 1));
    },

    'cli required mapped xlsx parity fails on skipped and mismatched evidence' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture): void {
        $missingRoot = $makeTempDir();
        $missing = $missingRoot . '/missing/test/xlsx-reader';
        try {
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-xlsx-native-ast.php')
                . ' --upstream-xlsx-dir='
                . escapeshellarg($missing)
                . ' --json'
                . ' summary'
                . ' --require-mapped-parity=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same('skipped', $decoded['status']);
            $t->same(true, $decoded['skipped']);
            $t->same(false, XlsxNativeAstComparisonHarness::hasRequiredMappedParity($decoded, 1));
        } finally {
            $removeTree($missingRoot);
        }

        $mismatchRoot = $makeTempDir();
        try {
            $copyBasicFixture($mismatchRoot, 'different');
            $native = (string) file_get_contents($mismatchRoot . '/different.native');
            file_put_contents($mismatchRoot . '/different.native', str_replace('Str "Anton"', 'Str "Antonia"', $native));

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-xlsx-native-ast.php')
                . ' --upstream-xlsx-dir='
                . escapeshellarg($mismatchRoot)
                . ' --json'
                . ' summary'
                . ' --require-mapped-parity=1';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same('completed', $decoded['status']);
            $t->same(1, $decoded['comparedPairCount']);
            $t->same(0, $decoded['normalizedAstMatchCount']);
            $t->same(1, $decoded['normalizedAstMismatchCount']);
            $t->same(false, XlsxNativeAstComparisonHarness::hasRequiredMappedParity($decoded, 1));
        } finally {
            $removeTree($mismatchRoot);
        }
    },

    'normalizes xlsx review provenance without hiding semantic attrs' => static function (TestRunner $t): void {
        $harness = new XlsxNativeAstComparisonHarness();
        $method = new ReflectionMethod(XlsxNativeAstComparisonHarness::class, 'normalizedNode');

        $xlsxCell = new AstNode('table_cell', [
            'text' => 'Merged Header',
            'sourceCell' => 'A1',
            'sourceColumn' => 0,
            'xlsxValueType' => 'shared-string',
            'xlsxStyleIndex' => 1,
            'colspan' => 3,
        ], [
            new AstNode('plain', [], [
                new AstNode('strong', [], [
                    new AstNode('text', ['text' => 'Merged Header']),
                ]),
            ]),
        ]);
        $nativeCell = new AstNode('table_cell', [
            'text' => 'Merged Header',
            'colspan' => 3,
        ], [
            new AstNode('plain', [], [
                new AstNode('strong', [], [
                    new AstNode('text', ['text' => 'Merged Header']),
                ]),
            ]),
        ]);

        $normalizedXlsx = $method->invoke($harness, $xlsxCell);
        $normalizedNative = $method->invoke($harness, $nativeCell);

        $t->same($normalizedNative, $normalizedXlsx);
        $t->same(3, $normalizedXlsx['attrs']['colspan']);
        $t->true(!array_key_exists('sourceCell', $normalizedXlsx['attrs']));
        $t->true(!array_key_exists('xlsxStyleIndex', $normalizedXlsx['attrs']));
    },
];
