<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxUpstreamRunnerPlan;

$makeTempRoot = static function (): string {
    $root = sys_get_temp_dir() . '/pandoc-docx-runner-plan-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create temporary runner plan root');
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

$writeFile = static function (string $root, string $relativePath, string $contents = "fixture\n"): void {
    $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $directory = dirname($path);
    if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to create runner plan fixture directory');
    }

    file_put_contents($path, $contents);
};

return [
    'reports blocked docx runner preflight without hydrated upstream source' => static function (TestRunner $t) use ($makeTempRoot, $removeTree): void {
        $repoRoot = $makeTempRoot();
        try {
            $report = (new DocxUpstreamRunnerPlan($repoRoot))->report();
            $text = DocxUpstreamRunnerPlan::formatTextReport($report);

            $t->same(DocxUpstreamRunnerPlan::STATUS_BLOCKED_MISSING_UPSTREAM_SOURCE, $report['status']);
            $t->same('targeted-docx-runner-preflight-plan-only', $report['evidenceKind']);
            $t->same(false, $report['runnerExecuted']);
            $t->same(false, $report['resultRecorded']);
            $t->same(false, $report['willExecute']);
            $t->same(DocxUpstreamRunnerPlan::requiredFiles(), $report['sourcePreflight']['missingFiles']);
            $t->same(DocxUpstreamRunnerPlan::requiredDirectories(), $report['sourcePreflight']['missingDirectories']);
            $t->same(0, $report['sourcePreflight']['artifactCounts']['rootDocxPackageFiles']);
            $t->same(0, $report['sourcePreflight']['artifactCounts']['rootNativeExpectedFiles']);
            $t->same(0, $report['sourcePreflight']['artifactCounts']['goldenDocxPackageFiles']);
            $t->contains('--dry-run --only-dependencies', $report['commands']['dependencyDryRun']['commandLine']);
            $t->contains('--list-tests --pattern', $report['commands']['listDocxTests']['commandLine']);
            $t->contains('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $report['commands']['targetedDocxRun']['commandLine']);
            $t->contains('not an upstream DOCX runner result', $report['claim']);
            $t->contains('No upstream DOCX runner result or parity claim is asserted.', $text);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'marks docx runner preflight ready with source files fixtures and artifact contract only' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile): void {
        $repoRoot = $makeTempRoot();
        try {
            $upstreamRoot = $repoRoot . '/cache/pandoc-current';
            foreach (DocxUpstreamRunnerPlan::requiredFiles() as $relativePath) {
                $writeFile($upstreamRoot, $relativePath, $relativePath . "\n");
            }
            $writeFile($upstreamRoot, 'test/docx/a.docx', 'docx-a');
            $writeFile($upstreamRoot, 'test/docx/a.native', 'native-a');
            $writeFile($upstreamRoot, 'test/docx/b.docx', 'docx-b');
            $writeFile($upstreamRoot, 'test/docx/golden/writer.docx', 'golden-writer');

            $report = (new DocxUpstreamRunnerPlan($repoRoot, 'cache/pandoc-current'))->report();

            $t->same(DocxUpstreamRunnerPlan::STATUS_READY, $report['status']);
            $t->same([], $report['sourcePreflight']['missingFiles']);
            $t->same([], $report['sourcePreflight']['missingDirectories']);
            $t->same(DocxUpstreamRunnerPlan::requiredFiles(), $report['sourcePreflight']['presentFiles']);
            $t->same(DocxUpstreamRunnerPlan::requiredDirectories(), $report['sourcePreflight']['presentDirectories']);
            $t->same(2, $report['sourcePreflight']['artifactCounts']['rootDocxPackageFiles']);
            $t->same(1, $report['sourcePreflight']['artifactCounts']['rootNativeExpectedFiles']);
            $t->same(1, $report['sourcePreflight']['artifactCounts']['goldenDocxPackageFiles']);
            $t->same('filesystem names/counts only; preflight does not read DOCX package bytes', $report['sourcePreflight']['packageBytePolicy']);
            $t->same('cache/pandoc-current', $report['commands']['targetedDocxRun']['workingDirectory']);
            $t->same('.port-libs/pandoc-runner/cabal-build/docx-targeted-run', $report['workspace']['directories']['targetedRunBuild']);
            $t->same('.port-libs/pandoc-runner/tmp', $report['workspace']['environmentVariables']['TMPDIR']);
            $t->same('test:test-pandoc', $report['runnerTarget']);
            $t->same('($2 == "Readers" || $2 == "Writers") && $3 == "Docx"', $report['tastyPattern']);
            $t->contains('.port-libs/pandoc-runner/logs/docx-targeted-run.txt', implode(',', $report['resultArtifactContract']['requiredBeforeResultRecorded']));
            $t->contains('result.json', implode(',', $report['resultArtifactContract']['requiredBeforeResultRecorded']));
            $t->contains('exitCode', implode(',', $report['resultArtifactContract']['resultJsonRequiredFields']));
            $t->contains('record dependencyDryRun transcript first', implode("\n", $report['activationGate']));
            $t->same(false, $report['runnerExecuted']);
            $t->same(false, $report['resultRecorded']);
        } finally {
            $removeTree($repoRoot);
        }
    },

    'rejects empty roots before emitting runner commands' => static function (TestRunner $t): void {
        $t->throws(InvalidArgumentException::class, static fn (): DocxUpstreamRunnerPlan => new DocxUpstreamRunnerPlan(''));
        $t->throws(InvalidArgumentException::class, static fn (): DocxUpstreamRunnerPlan => new DocxUpstreamRunnerPlan(__DIR__, ''));
    },
];
