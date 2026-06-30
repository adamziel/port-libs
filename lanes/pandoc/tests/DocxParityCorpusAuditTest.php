<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxParityCorpusAudit;
use PortLibs\Pandoc\ZipPackage;

$makeTempRoot = static function (): string {
    $root = sys_get_temp_dir() . '/pandoc-docx-parity-audit-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create temporary audit root');
    }

    return $root;
};

$removeTree = static function (string $path) use (&$removeTree): void {
    if (!is_dir($path)) {
        return;
    }

    foreach (new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    ) as $entry) {
        if ($entry->isDir()) {
            rmdir($entry->getPathname());
        } else {
            unlink($entry->getPathname());
        }
    }

    rmdir($path);
};

$writeFile = static function (string $root, string $relativePath, string $contents): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create fixture directory');
    }

    file_put_contents($path, $contents);
};

$minimalDocx = static function (string $text): string {
    $xmlText = htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');

    return ZipPackage::build([
        [
            'name' => 'word/document.xml',
            'data' => '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>' . $xmlText . '</w:t></w:r></w:p></w:body></w:document>',
        ],
    ]);
};

return [
    'skips cleanly when local upstream docx cache is absent' => static function (TestRunner $t) use ($makeTempRoot, $removeTree): void {
        $root = $makeTempRoot();
        try {
            $report = (new DocxParityCorpusAudit($root))->report();
            $text = DocxParityCorpusAudit::formatTextReport($report);

            $t->same(DocxParityCorpusAudit::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(true, $report['skipped']);
            $t->same('parser-acceptance-only', $report['evidenceKind']);
            $t->same(false, $report['sourceDirectoryPresent']);
            $t->same(0, $report['auditedPairCount']);
            $t->same(0, $report['bothParsedCount']);
            $t->same(null, $report['bothParserCoveragePercent']);
            $t->same(DocxParityCorpusAudit::PARSER_ACCEPTANCE_BASELINE_NAME, $report['parserAcceptanceBaseline']['baselineName']);
            $t->same(74, $report['parserAcceptanceBaseline']['pairedDocxNativeArtifacts']);
            $t->same(false, $report['parserAcceptanceRegression']['evaluated']);
            $t->same(false, $report['parserAcceptanceRegression']['regressed']);
            $t->same('not-evaluated-source-directory-unavailable', $report['parserAcceptanceRegression']['reason']);
            $t->contains('Result: skipped', $text);
            $t->contains('No DOCX parity is asserted.', $text);
        } finally {
            $removeTree($root);
        }
    },

    'reports paired upstream docx native parser coverage without asserting parity' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/sample.docx", $minimalDocx('Hello audit'));
            $writeFile($root, "{$docxRoot}/sample.native", '[ Para [ Str "Hello" , Space , Str "audit" ] ]');
            $writeFile($root, "{$docxRoot}/broken.docx", 'not a zip package');
            $writeFile($root, "{$docxRoot}/broken.native", '[ Para [ Str "unterminated ]');
            $writeFile($root, "{$docxRoot}/orphan-docx.docx", $minimalDocx('Orphan DOCX'));
            $writeFile($root, "{$docxRoot}/orphan-native.native", '[ Para [ Str "Orphan" ] ]');
            $writeFile($root, "{$docxRoot}/golden/writer-output.docx", $minimalDocx('Writer inventory'));

            $report = (new DocxParityCorpusAudit($root))->report();
            $text = DocxParityCorpusAudit::formatTextReport($report);

            $t->same(DocxParityCorpusAudit::STATUS_REPORTED, $report['status']);
            $t->same(false, $report['skipped']);
            $t->same(DocxParityCorpusAudit::VERDICT, $report['verdict']);
            $t->same('parser-acceptance-only', $report['evidenceKind']);
            $t->same(6, $report['rootDirectoryArtifactCount']);
            $t->same(3, $report['rootDocxPackageArtifacts']);
            $t->same(3, $report['rootNativeExpectedArtifacts']);
            $t->same(1, $report['goldenDocxPackageArtifacts']);
            $t->same(2, $report['pairedDocxNativeArtifacts']);
            $t->same(1, $report['unpairedDocxPackageArtifacts']);
            $t->same(1, $report['unpairedNativeExpectedArtifacts']);
            $t->same(['orphan-docx'], $report['docxWithoutNativeSamples']);
            $t->same(['orphan-native'], $report['nativeWithoutDocxSamples']);
            $t->same(2, $report['auditedPairCount']);
            $t->same(1, $report['docxParsedCount']);
            $t->same(1, $report['docxFailedCount']);
            $t->same(1, $report['nativeParsedCount']);
            $t->same(1, $report['nativeFailedCount']);
            $t->same(1, $report['bothParsedCount']);
            $t->same(1, $report['bothFailedOrPartialCount']);
            $t->same(50.0, $report['bothParserCoveragePercent']);
            $t->same('broken', $report['pairRows'][0]['name']);
            $t->same('failed', $report['pairRows'][0]['docxParse']['status']);
            $t->same('failed', $report['pairRows'][0]['nativeParse']['status']);
            $t->same('sample', $report['pairRows'][1]['name']);
            $t->same('parsed', $report['pairRows'][1]['docxParse']['status']);
            $t->same('parsed', $report['pairRows'][1]['nativeParse']['status']);
            $t->same(1, count($report['failureRows']));
            $t->same(DocxParityCorpusAudit::PARSER_ACCEPTANCE_BASELINE_NAME, $report['parserAcceptanceBaseline']['baselineName']);
            $t->same(true, $report['parserAcceptanceRegression']['evaluated']);
            $t->same(false, $report['parserAcceptanceRegression']['passed']);
            $t->same(true, $report['parserAcceptanceRegression']['regressed']);
            $t->true(in_array('paired-docx-native-artifact-count-below-baseline', $report['parserAcceptanceRegression']['failureReasons'], true));
            $t->true(in_array('docx-parse-failures-present', $report['parserAcceptanceRegression']['failureReasons'], true));
            $t->true(DocxParityCorpusAudit::hasParserAcceptanceRegression($report));
            $t->contains('Both parsers accepted: 1/2 (50.00%)', $text);
            $t->contains('Parser acceptance regression guard: failed', $text);
            $t->contains('No AST equality, upstream Haskell runner, or DOCX writer golden package parity is asserted.', $text);
        } finally {
            $removeTree($root);
        }
    },

    'respects pair audit limits and emits cli json for absent cache' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/a.docx", $minimalDocx('A'));
            $writeFile($root, "{$docxRoot}/a.native", '[ Para [ Str "A" ] ]');
            $writeFile($root, "{$docxRoot}/b.docx", $minimalDocx('B'));
            $writeFile($root, "{$docxRoot}/b.native", '[ Para [ Str "B" ] ]');

            $report = (new DocxParityCorpusAudit($root))->report(1);
            $t->same(2, $report['pairedDocxNativeArtifacts']);
            $t->same(1, $report['auditedPairCount']);
            $t->same(1, $report['unauditedPairCount']);
            $t->same(1, $report['bothParsedCount']);

            $missingRoot = $makeTempRoot();
            try {
                $command = escapeshellarg(PHP_BINARY)
                    . ' '
                    . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-parity-audit.php')
                    . ' --repo-root='
                    . escapeshellarg($missingRoot)
                    . ' --json'
                    . ' --fail-on-regression';
                $output = [];
                $exitCode = 0;
                exec($command, $output, $exitCode);
                $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

                $t->same(0, $exitCode);
                $t->same(DocxParityCorpusAudit::STATUS_SKIPPED_MISSING_SOURCE, $decoded['status']);
                $t->same(true, $decoded['skipped']);
                $t->same(false, $decoded['parserAcceptanceRegression']['evaluated']);
                $t->same(false, $decoded['parserAcceptanceRegression']['regressed']);
            } finally {
                $removeTree($missingRoot);
            }
        } finally {
            $removeTree($root);
        }
    },

    'cli fail-on-regression exits nonzero when parser acceptance is below baseline' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $minimalDocx): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/sample.docx", $minimalDocx('Hello audit'));
            $writeFile($root, "{$docxRoot}/sample.native", '[ Para [ Str "Hello" , Space , Str "audit" ] ]');

            $command = escapeshellarg(PHP_BINARY)
                . ' '
                . escapeshellarg(dirname(__DIR__, 3) . '/tools/pandoc-docx-parity-audit.php')
                . ' --repo-root='
                . escapeshellarg($root)
                . ' --json'
                . ' --fail-on-regression';
            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);
            $decoded = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            $t->same(1, $exitCode);
            $t->same(DocxParityCorpusAudit::STATUS_REPORTED, $decoded['status']);
            $t->same(true, $decoded['parserAcceptanceRegression']['evaluated']);
            $t->same(false, $decoded['parserAcceptanceRegression']['passed']);
            $t->same(true, $decoded['parserAcceptanceRegression']['regressed']);
            $t->same(1, $decoded['parserAcceptanceRegression']['actualBothParsedCount']);
            $t->true(in_array('paired-docx-native-artifact-count-below-baseline', $decoded['parserAcceptanceRegression']['failureReasons'], true));
        } finally {
            $removeTree($root);
        }
    },
];
