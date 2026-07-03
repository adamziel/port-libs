<?php

declare(strict_types=1);

use PortLibs\Pandoc\ManCorpusAudit;

$makeTempDir = static function (): string {
    $base = tempnam(sys_get_temp_dir(), 'pandoc-man-corpus-');
    if ($base === false) {
        throw new RuntimeException('Unable to allocate temporary man corpus directory');
    }
    @unlink($base);
    if (!mkdir($base, 0777, true) && !is_dir($base)) {
        throw new RuntimeException("Unable to create temporary man corpus directory {$base}");
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

$writeFakePandoc = static function (string $path): void {
    $native = <<<'SH'
#!/bin/sh
if [ "$1" = "--version" ]; then
  echo "pandoc fake man 1.0"
  exit 0
fi
input="$(cat)"
case "$input" in
  *MDOCTOOL*)
    cat <<'NATIVE'
[ Header 1 ( "" , [] , [] ) [ Str "NAME" ]
, Para
    [ Code ( "" , [] , [] ) "mdoctool"
    , Str ""
    , Space
    , Str "\8212"
    , Space
    , Str "synthetic"
    , Space
    , Str "mdoc"
    , Space
    , Str "fixture"
    ]
, Header 1 ( "" , [] , [] ) [ Str "DESCRIPTION" ]
, Para
    [ Str "This"
    , Space
    , Str "file"
    , Space
    , Str "is"
    , Space
    , Str "intentionally"
    , Space
    , Str "outside"
    , Space
    , Str "the"
    , Space
    , Str "man-dialect"
    , Space
    , Str "audit"
    , Space
    , Str "target."
    ]
]
NATIVE
    ;;
  *SIMPLE*)
    cat <<'NATIVE'
[ Header 1 ("",[],[]) [ Str "NAME" ]
, Para [ Str "simple", Space, Str "-", Space, Str "small", Space, Str "command" ]
, Header 1 ("",[],[]) [ Str "DESCRIPTION" ]
, Para [ Str "simple", Space, Str "prints", Space, Str "text." ]
]
NATIVE
    ;;
  *)
    echo '[ Para [ Str "different" ] ]'
    ;;
esac
SH;
    file_put_contents($path, $native);
    chmod($path, 0755);
};

return [
    'audits fixture corpus by dialect and pandoc comparison' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFakePandoc): void {
        $root = dirname(__DIR__) . '/fixtures/man-corpus-smoke';
        $temp = $makeTempDir();
        try {
            $fakePandoc = $temp . '/pandoc';
            $writeFakePandoc($fakePandoc);

            $report = (new ManCorpusAudit())->run([$root], ['pandocBin' => $fakePandoc]);
            $text = (new ManCorpusAudit())->formatReport($report);

            $t->same('completed', $report['status']);
            $t->same(3, $report['totalCandidateFileCount']);
            $t->same(3, $report['auditedFileCount']);
            $t->same(['man' => 2, 'mdoc' => 1], $report['dialectCounts']);
            $t->same(2, $report['targetFileCount']);
            $t->same(1, $report['nonTargetFileCount']);
            $t->same(2, $report['localParsedCount']);
            $t->same(0, $report['localParseFailureCount']);
            $t->same(0, $report['localControlLeakCount']);
            $t->same('completed', $report['pandocComparisonStatus']);
            $t->same('pandoc fake man 1.0', $report['pandocVersion']);
            $t->same(2, $report['pandocParsedCount']);
            $t->same(2, $report['bothParsedCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(1, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['visibleTextMatchCount']);
            $t->same('local-and-pandoc-parse-accepted-with-normalized-ast-drift', $report['corpusStatus']);
            $t->same('covered-by-current-corpus-audit', $report['orderedRemainingGaps'][0]['status']);
            $t->same('open', $report['orderedRemainingGaps'][1]['status']);
            $t->same('open', $report['orderedRemainingGaps'][2]['status']);
            $t->same(true, ManCorpusAudit::hasRequiredTargetFiles($report, 2));
            $t->same(true, ManCorpusAudit::hasRequiredLocalParse($report, 2));
            $t->same(true, ManCorpusAudit::hasNoLocalParseFailures($report));
            $t->same(true, ManCorpusAudit::hasNoControlLeaks($report));
            $t->same(true, ManCorpusAudit::hasRequiredPandocParse($report, 2));
            $t->same(true, ManCorpusAudit::hasRequiredNormalizedMatches($report, 1));
            $t->contains('Pandoc man corpus audit: completed', $text);
            $t->contains('mdoc-dialect-support [open]', $text);
        } finally {
            $removeTree($temp);
        }
    },

    'audits mdoc target dialect with mdoc reader and pandoc format' => static function (TestRunner $t) use ($makeTempDir, $removeTree, $writeFakePandoc): void {
        $root = dirname(__DIR__) . '/fixtures/man-corpus-smoke';
        $temp = $makeTempDir();
        try {
            $fakePandoc = $temp . '/pandoc';
            $writeFakePandoc($fakePandoc);

            $report = (new ManCorpusAudit())->run([$root], [
                'pandocBin' => $fakePandoc,
                'targetDialects' => ['mdoc'],
            ]);
            $text = (new ManCorpusAudit())->formatReport($report);

            $t->same(['mdoc'], $report['targetDialects']);
            $t->same(['man' => 2, 'mdoc' => 1], $report['dialectCounts']);
            $t->same(1, $report['targetFileCount']);
            $t->same(2, $report['nonTargetFileCount']);
            $t->same(1, $report['localParsedCount']);
            $t->same(0, $report['localParseFailureCount']);
            $t->same(0, $report['localControlLeakCount']);
            $t->same(1, $report['pandocParsedCount']);
            $t->same(1, $report['bothParsedCount']);
            $t->same(1, $report['normalizedAstMatchCount']);
            $t->same(0, $report['normalizedAstMismatchCount']);
            $t->same(1, $report['visibleTextMatchCount']);
            $t->same('local-and-pandoc-normalized-ast-equality-observed', $report['corpusStatus']);
            $t->same('covered-by-current-mdoc-audit-lane', $report['orderedRemainingGaps'][2]['status']);
            $t->contains('mdoc-dialect-support [covered-by-current-mdoc-audit-lane]', $text);
        } finally {
            $removeTree($temp);
        }
    },

    'reads gzip manpage sources and skips pandoc when disabled' => static function (TestRunner $t) use ($makeTempDir, $removeTree): void {
        $root = $makeTempDir();
        try {
            mkdir($root . '/man1');
            $source = ".TH \"GZIP\" \"1\"\n.SH NAME\ngzipfixture \\- compressed source\n";
            file_put_contents($root . '/man1/gzipfixture.1.gz', gzencode($source));

            $report = (new ManCorpusAudit())->run([$root], ['comparePandoc' => false]);

            $t->same(1, $report['totalCandidateFileCount']);
            $t->same(['man' => 1], $report['dialectCounts']);
            $t->same(1, $report['targetFileCount']);
            $t->same(1, $report['localParsedCount']);
            $t->same(0, $report['localParseFailureCount']);
            $t->same(0, $report['localControlLeakCount']);
            $t->same('skipped-pandoc-executable-missing-or-disabled', $report['pandocComparisonStatus']);
            $t->same('local-parse-acceptance-observed-pandoc-comparison-skipped', $report['corpusStatus']);
            $t->same('not-evaluated', $report['orderedRemainingGaps'][1]['status']);
        } finally {
            $removeTree($root);
        }
    },

    'cli gates local fixture corpus acceptance' => static function (TestRunner $t): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-man-corpus-audit.php')
            . ' --man-root=lanes/pandoc/fixtures/man-corpus-smoke'
            . ' --no-pandoc'
            . ' --json'
            . ' summary'
            . ' --require-target-files-min=2'
            . ' --require-local-parse-min=2'
            . ' --require-no-local-parse-failures'
            . ' --require-no-control-leaks';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(2, $decoded['targetFileCount']);
        $t->same(2, $decoded['localParsedCount']);
        $t->same(0, $decoded['localControlLeakCount']);

        $failingCommand = str_replace('--require-target-files-min=2', '--require-target-files-min=3', $command) . ' 2>/dev/null';
        $failingOutput = [];
        $failingExitCode = 0;
        exec($failingCommand, $failingOutput, $failingExitCode);

        $t->same(1, $failingExitCode);
    },

    'cli can target checked-in mdoc fixtures' => static function (TestRunner $t): void {
        $command = escapeshellarg(PHP_BINARY)
            . ' '
            . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-man-corpus-audit.php')
            . ' --man-root=lanes/pandoc/fixtures/man-corpus-smoke'
            . ' --target-dialect=mdoc'
            . ' --no-pandoc'
            . ' --json'
            . ' summary'
            . ' --require-target-files-min=1'
            . ' --require-local-parse-min=1'
            . ' --require-no-local-parse-failures'
            . ' --require-no-control-leaks';
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        $t->same(0, $exitCode);
        $t->same(['mdoc'], $decoded['targetDialects']);
        $t->same(1, $decoded['targetFileCount']);
        $t->same(1, $decoded['localParsedCount']);
    },
];
