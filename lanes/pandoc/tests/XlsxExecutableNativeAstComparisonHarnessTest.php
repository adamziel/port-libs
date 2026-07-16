<?php

declare(strict_types=1);

use PortLibs\Pandoc\XlsxExecutableNativeAstComparisonHarness;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-xlsx-exec-ast-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary XLSX executable AST directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary XLSX executable AST directory {$base}");
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

$writeFakePandoc = static function (string $path, string $nativePath): void {
    file_put_contents($path, "#!/bin/sh\nif [ \"\$1\" = \"--version\" ]; then echo \"pandoc fake 1.0\"; exit 0; fi\ncat " . escapeshellarg($nativePath) . "\n");
    chmod($path, 0755);
};

$copyBasicFixture = static function (string $root): void {
    $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-xlsx-reader';
    copy($fixtureRoot . '/basic.xlsx', $root . '/basic.xlsx');
    copy($fixtureRoot . '/basic.native', $root . '/basic.native');
};

return [
    'skips xlsx executable comparison when pandoc is absent' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            $report = (new XlsxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $root . '/missing-pandoc',
            ]);
            $text = (new XlsxExecutableNativeAstComparisonHarness())->formatReport($report);

            $t->same('skipped', $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('pandoc-executable-missing', $report['reason']);
            $t->same(1, $report['totalXlsxCount']);
            $t->same(0, $report['comparedXlsxCount']);
            $t->same(0, $report['nativeFixtureParsedCount']);
            $t->same(0, $report['pandocNativeFixtureMatchCount']);
            $t->same('not-evaluated-pandoc-executable-missing', $report['astParityStatus']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][0]['status']);
            $t->contains('Pandoc XLSX executable/native AST comparison: skipped', $text);
        } finally {
            $removeTree($root);
        }
    },

    'matches local xlsx reader output against fake pandoc native output' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/basic.native');

            $report = (new XlsxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(false, $report['skipped']);
            $t->same(1, $report['totalXlsxCount']);
            $t->same(1, $report['comparedXlsxCount']);
            $t->same(1, $report['localParsedCount']);
            $t->same(1, $report['pandocParsedCount']);
            $t->same(1, $report['nativeFixtureParsedCount']);
            $t->same(0, $report['parseFailureCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureComparedCount']);
            $t->same(1, $report['pandocNativeFixtureMatchCount']);
            $t->same(0, $report['pandocNativeFixtureMismatchCount']);
            $t->same('pandoc fake 1.0', $report['pandocVersion']);
            $t->same('normalized-ast-equality-observed-against-pandoc-executable', $report['astParityStatus']);
            $t->same(true, XlsxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
            $t->same(true, XlsxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($report, 'pandoc fake 1.0'));
            $t->same(false, XlsxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($report, 'pandoc fake 2.0'));
        } finally {
            $removeTree($root);
        }
    },

    'reports executable native ast mismatches without claiming parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            file_put_contents($root . '/mismatch.native', '[Para [Str "different"]]');
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/mismatch.native');

            $report = (new XlsxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(1, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['pandocNativeFixtureComparedCount']);
            $t->same(0, $report['pandocNativeFixtureMatchCount']);
            $t->same(1, $report['pandocNativeFixtureMismatchCount']);
            $t->same('normalized-ast-mismatches-observed', $report['astParityStatus']);
            $t->same('basic', $report['mismatchComparisons'][0]['fixture']);
            $t->contains('root.children keys', $report['mismatchComparisons'][0]['firstDifference']);
            $t->same('basic', $report['pandocNativeFixtureMismatchComparisons'][0]['fixture']);
            $t->contains('root.children keys', $report['pandocNativeFixtureMismatchComparisons'][0]['firstDifference']);
            $t->same(false, XlsxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
        } finally {
            $removeTree($root);
        }
    },

    'requires paired upstream native fixtures for executable parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-xlsx-reader';
            copy($fixtureRoot . '/basic.xlsx', $root . '/basic.xlsx');
            copy($fixtureRoot . '/basic.native', $root . '/expected.native');
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/expected.native');

            $report = (new XlsxExecutableNativeAstComparisonHarness())->run($root, [
                'pandocBin' => $fakePandoc,
            ]);

            $t->same('completed', $report['status']);
            $t->same(1, $report['localParsedCount']);
            $t->same(1, $report['pandocParsedCount']);
            $t->same(0, $report['nativeFixtureParsedCount']);
            $t->same(1, $report['bothParsedCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['pandocNativeFixtureComparedCount']);
            $t->same(1, $report['parseFailureCount']);
            $t->same('missing paired .native fixture', $report['parseFailures'][0]['nativeFixtureError']);
            $t->same('blocked-by-parse-failures', $report['astParityStatus']);
            $t->same(false, XlsxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($report, 1));
        } finally {
            $removeTree($root);
        }
    },

    'cli gates required executable xlsx parity with fake pandoc' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $copyBasicFixture, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $copyBasicFixture($root);
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $root . '/basic.native');
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-xlsx-executable-native-ast.php')
                . ' --xlsx-dir=' . escapeshellarg($root)
                . ' --pandoc-bin=' . escapeshellarg($fakePandoc)
                . ' --json'
                . ' summary'
                . ' --require-executable-parity=1'
                . ' --require-pandoc-version=' . escapeshellarg('pandoc fake 1.0');
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same(1, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(1, $decoded['pandocNativeFixtureComparedCount']);
            $t->same(1, $decoded['pandocNativeFixtureMatchCount']);
            $t->same(0, $decoded['pandocNativeFixtureMismatchCount']);
            $t->same(true, XlsxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($decoded, 1));
            $t->same(true, XlsxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($decoded, 'pandoc fake 1.0'));

            $missingCommand = str_replace('--pandoc-bin=' . escapeshellarg($fakePandoc), '--pandoc-bin=' . escapeshellarg($root . '/missing'), $command) . ' 2>/dev/null';
            $missingOutput = [];
            $missingExitCode = 0;
            exec($missingCommand, $missingOutput, $missingExitCode);

            $t->same(1, $missingExitCode);

            $versionMismatchCommand = str_replace(
                '--require-pandoc-version=' . escapeshellarg('pandoc fake 1.0'),
                '--require-pandoc-version=' . escapeshellarg('pandoc fake 2.0'),
                $command
            ) . ' 2>/dev/null';
            $versionMismatchOutput = [];
            $versionMismatchExitCode = 0;
            exec($versionMismatchCommand, $versionMismatchOutput, $versionMismatchExitCode);

            $t->same(1, $versionMismatchExitCode);
        } finally {
            $removeTree($root);
        }
    },

    'cli accepts checked-in xlsx fixtures for executable parity' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFakePandoc): void {
        $root = $makeTempDir();
        try {
            $fixtureRoot = dirname(__DIR__) . '/fixtures/upstream-current-xlsx-reader';
            $fakePandoc = $root . '/pandoc';
            $writeFakePandoc($fakePandoc, $fixtureRoot . '/basic.native');
            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-xlsx-executable-native-ast.php')
                . ' --checked-in-fixtures'
                . ' --pandoc-bin=' . escapeshellarg($fakePandoc)
                . ' --json'
                . ' summary'
                . ' --require-executable-parity=1'
                . ' --require-pandoc-version=' . escapeshellarg('pandoc fake 1.0');
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(0, $exitCode);
            $t->same('completed', $decoded['status']);
            $t->same(1, $decoded['comparedXlsxCount']);
            $t->same(1, $decoded['normalizedAstMatchCount']);
            $t->same(0, $decoded['normalizedAstMismatchCount']);
            $t->same(1, $decoded['pandocNativeFixtureComparedCount']);
            $t->same(1, $decoded['pandocNativeFixtureMatchCount']);
            $t->same(0, $decoded['pandocNativeFixtureMismatchCount']);
            $t->same(true, XlsxExecutableNativeAstComparisonHarness::hasRequiredExecutableParity($decoded, 1));
            $t->same(true, XlsxExecutableNativeAstComparisonHarness::hasRequiredPandocVersion($decoded, 'pandoc fake 1.0'));
        } finally {
            $removeTree($root);
        }
    },
];
