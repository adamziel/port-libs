<?php

declare(strict_types=1);

use PortLibs\Pandoc\DocxUpstreamCacheManifest;

$makeTempRoot = static function (): string {
    $root = sys_get_temp_dir() . '/pandoc-docx-cache-manifest-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create temporary manifest root');
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
        throw new RuntimeException('Unable to create manifest fixture directory');
    }

    file_put_contents($path, $contents);
};

$rowsByPath = static function (array $rows): array {
    $byPath = [];
    foreach ($rows as $row) {
        $byPath[(string) $row['path']] = $row;
    }

    return $byPath;
};

return [
    'records optional upstream docx native golden stems and hashes without parity claims' => static function (TestRunner $t) use ($makeTempRoot, $removeTree, $writeFile, $rowsByPath): void {
        $root = $makeTempRoot();
        try {
            $docxRoot = '.upstream-cache/pandoc-current/test/docx';
            $writeFile($root, "{$docxRoot}/a.docx", 'docx-a');
            $writeFile($root, "{$docxRoot}/a.native", 'native-a');
            $writeFile($root, "{$docxRoot}/b.docx", 'docx-b');
            $writeFile($root, "{$docxRoot}/c.native", 'native-c');
            $writeFile($root, "{$docxRoot}/ignored.txt", 'ignored');
            $writeFile($root, "{$docxRoot}/golden/writer.docx", 'golden-writer');

            $report = (new DocxUpstreamCacheManifest($root))->report([
                'observedUpstreamCommit' => DocxUpstreamCacheManifest::CURRENT_UPSTREAM_COMMIT,
                'workingTreeCleanForTestDocx' => true,
                'upstreamRootDisplay' => '.upstream-cache/pandoc-current',
            ]);
            $text = DocxUpstreamCacheManifest::formatTextReport($report);

            $t->same(DocxUpstreamCacheManifest::STATUS_REPORTED, $report['status']);
            $t->same(false, $report['skipped']);
            $t->same('artifact-identity-manifest-only', $report['evidenceKind']);
            $t->same(DocxUpstreamCacheManifest::CURRENT_UPSTREAM_COMMIT, $report['upstream']['commit']);
            $t->same(true, $report['upstream']['commitMatchesExpected']);
            $t->same(true, $report['source']['workingTreeCleanForTestDocx']);
            $t->same(5, $report['artifactCounts']['totalDocxNativeGoldenArtifacts']);
            $t->same(2, $report['artifactCounts']['rootDocxPackageArtifacts']);
            $t->same(2, $report['artifactCounts']['rootNativeExpectedArtifacts']);
            $t->same(4, $report['artifactCounts']['rootDocxAndNativeArtifacts']);
            $t->same(1, $report['artifactCounts']['goldenDocxPackageArtifacts']);
            $t->same(3, $report['artifactCounts']['totalDocxPackageArtifacts']);
            $t->same(1, $report['artifactCounts']['pairedRootDocxNativeStems']);
            $t->same(1, $report['artifactCounts']['unpairedRootDocxPackageStems']);
            $t->same(1, $report['artifactCounts']['unpairedRootNativeExpectedStems']);
            $t->same(['a', 'b'], $report['rootDocxPackageStems']);
            $t->same(['a', 'c'], $report['rootNativeExpectedStems']);
            $t->same(['a'], $report['pairedRootDocxNativeStems']);
            $t->same(['b'], $report['unpairedRootDocxPackageStems']);
            $t->same(['c'], $report['unpairedRootNativeExpectedStems']);
            $t->same(['writer'], $report['goldenDocxPackageStems']);

            $rows = $rowsByPath($report['artifactRows']);
            $t->same('root-docx-package', $rows['test/docx/a.docx']['kind']);
            $t->same(6, $rows['test/docx/a.docx']['bytes']);
            $t->same(hash('sha256', 'docx-a'), $rows['test/docx/a.docx']['sha256']);
            $t->same('root-native-expected', $rows['test/docx/a.native']['kind']);
            $t->same(hash('sha256', 'native-a'), $rows['test/docx/a.native']['sha256']);
            $t->same('golden-docx-package', $rows['test/docx/golden/writer.docx']['kind']);
            $t->same(hash('sha256', 'golden-writer'), $rows['test/docx/golden/writer.docx']['sha256']);

            $digestRows = array_map(
                static fn (array $row): array => [
                    'kind' => $row['kind'],
                    'path' => $row['path'],
                    'bytes' => $row['bytes'],
                    'sha256' => $row['sha256'],
                ],
                $report['artifactRows']
            );
            $t->same(
                hash('sha256', json_encode($digestRows, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
                $report['artifactSetSha256']
            );
            $t->true(in_array('checked-in DOCX package bytes', $report['claimBoundaries']['doesNotAssert'], true));
            $t->true(in_array('full DOCX/OpenXML semantic parity', $report['claimBoundaries']['doesNotAssert'], true));
            $t->contains('No package bytes, AST equality, upstream runner parity, or writer golden parity is asserted.', $text);
        } finally {
            $removeTree($root);
        }
    },

    'skips cleanly when optional upstream cache is absent' => static function (TestRunner $t) use ($makeTempRoot, $removeTree): void {
        $root = $makeTempRoot();
        try {
            $report = (new DocxUpstreamCacheManifest($root))->report();
            $text = DocxUpstreamCacheManifest::formatTextReport($report);

            $t->same(DocxUpstreamCacheManifest::STATUS_SKIPPED_MISSING_SOURCE, $report['status']);
            $t->same(true, $report['skipped']);
            $t->same(0, $report['artifactCounts']['totalDocxNativeGoldenArtifacts']);
            $t->same([], $report['artifactRows']);
            $t->same(hash('sha256', '[]'), $report['artifactSetSha256']);
            $t->contains('Result: skipped', $text);
            $t->contains('No artifact identity or DOCX parity is asserted.', $text);
        } finally {
            $removeTree($root);
        }
    },
];
