<?php

declare(strict_types=1);

use PortLibs\Pandoc\UpstreamRunnerDependencyAudit;

$makeTree = static function (array $files): string {
    $root = sys_get_temp_dir() . '/pandoc-runner-audit-' . bin2hex(random_bytes(6));
    if (!mkdir($root, 0777, true) && !is_dir($root)) {
        throw new RuntimeException('Unable to create runner audit fixture directory');
    }

    foreach ($files as $relativePath => $contents) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, (string) $relativePath);
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            throw new RuntimeException('Unable to create runner audit fixture subdirectory');
        }
        file_put_contents($path, (string) $contents);
    }

    return $root;
};

$removeTree = static function (string $root): void {
    if (!is_dir($root)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->isDir()) {
            rmdir($fileInfo->getPathname());
        } else {
            unlink($fileInfo->getPathname());
        }
    }
    rmdir($root);
};

$pinnedProject = static function (array $overrides = []): string {
    $pins = array_merge(UpstreamRunnerDependencyAudit::expectedProjectPins(), $overrides);
    $lines = [
        'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
        '',
    ];

    foreach ($pins as $name => $tag) {
        $lines[] = 'source-repository-package';
        $lines[] = '  type: git';
        $lines[] = '  location: https://github.com/jgm/' . $name . '.git';
        $lines[] = '  tag: ' . $tag;
        $lines[] = '';
    }

    return implode("\n", $lines);
};

$requiredFiles = static function (string $project): array {
    return [
        'cabal.project' => $project,
        'pandoc.cabal' => 'test-suite test-pandoc',
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => 'test-suite test-pandoc-lua-engine',
        'test/test-pandoc.hs' => 'main = pure ()',
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs' => 'main = pure ()',
    ];
};

return [
    'reports missing checkout files and cabal tools without invoking runners' => static function (TestRunner $t) use ($makeTree, $removeTree): void {
        $root = $makeTree([]);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => false, 'version' => null],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([
            'cabal.project',
            'pandoc.cabal',
            'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'test/test-pandoc.hs',
            'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
        ], $audit['missingFiles']);
        $t->same(['cabal'], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['present']);
        $t->same([
            'doclayout',
            'typst-symbols',
            'typst-hs',
            'texmath',
            'citeproc',
        ], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['nonMutatingPlan']);
        $t->contains('missing required upstream runner files', $audit['blockedReasons'][0]);
        $t->contains('missing required Cabal toolchain commands: cabal', $audit['blockedReasons'][1]);
        $t->contains(UpstreamRunnerDependencyAudit::UPSTREAM_COMMIT, $audit['activationGate']);
    },
    'accepts hydrated cabal runner closure with exact project source pins' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject()));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $expectedPins = UpstreamRunnerDependencyAudit::expectedProjectPins();
        ksort($expectedPins);
        $t->same($expectedPins, $audit['projectSourceRepositoryPins']['present']);
        $t->same(['test:test-pandoc', 'test:test-pandoc-lua-engine'], $audit['runnerTargets']);
        $t->same('pandoc.cabal', $audit['runnerEntryPoints']['test:test-pandoc']['packageFile']);
        $t->same('pandoc-lua-engine/pandoc-lua-engine.cabal', $audit['runnerEntryPoints']['test:test-pandoc-lua-engine']['packageFile']);
        $t->contains('non-mutating solver/build plan', $audit['activationGate']);
        $t->contains('record cabal.project and package-file hashes', $audit['nonMutatingPlan'][0]);
    },
    'flags missing and mismatched cabal project git pins' => static function (TestRunner $t) use ($makeTree, $removeTree, $requiredFiles): void {
        $project = implode("\n", [
            'packages: . pandoc-lua-engine',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/doclayout.git',
            '  tag: wrong-doclayout-tag',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/texmath.git',
            '  tag: 0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
        ]);
        $root = $makeTree($requiredFiles($project));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['missingFiles']);
        $t->same([], $audit['missingTools']);
        $t->same([
            'typst-symbols',
            'typst-hs',
            'citeproc',
        ], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([
            'expected' => 'ef7f18308a61787244a80885d907fcd2c16604d4',
            'actual' => 'wrong-doclayout-tag',
        ], $audit['projectSourceRepositoryPins']['mismatched']['doclayout']);
        $t->contains('missing cabal.project source-repository pins', $audit['blockedReasons'][0]);
        $t->contains('mismatched cabal.project source-repository pins: doclayout', $audit['blockedReasons'][1]);
    },
];
