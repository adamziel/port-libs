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
        'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1',
        '',
        'package pandoc',
        '  flags: +embed_data_files +http',
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

$pandocCabal = static function (array $without = [], ?string $mainIs = null, ?string $sourceDirectory = null, ?string $ghcOptions = null, string $type = 'exitcode-stdio-1.0'): string {
    $dependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedRunnerDependencies()['test:test-pandoc'],
        $without
    ));
    $commonDependencies = array_values(array_intersect($dependencies, ['base', 'pandoc']));
    $suiteDependencies = array_values(array_diff($dependencies, $commonDependencies));
    $commonExecutable = [
        'common common-executable',
        '  import: common-options',
    ];
    if ($ghcOptions !== '') {
        $commonExecutable[] = '  ghc-options: ' . ($ghcOptions ?? '-rtsopts -with-rtsopts=-A8m -threaded');
    }

    return implode("\n", array_merge([
        'common common-options',
        '  build-depends: ' . implode(', ', $commonDependencies),
        '',
    ], $commonExecutable, [
        '',
        'test-suite test-pandoc',
        '  import: common-executable',
        '  type: ' . $type,
        '  main-is: ' . ($mainIs ?? 'test-pandoc.hs'),
        '  hs-source-dirs: ' . ($sourceDirectory ?? 'test'),
        '  build-depends:',
        '    ' . implode(",\n    ", $suiteDependencies),
    ]));
};

$luaCabal = static function (array $without = [], ?string $mainIs = null, ?string $sourceDirectory = null, string $type = 'exitcode-stdio-1.0'): string {
    $dependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedRunnerDependencies()['test:test-pandoc-lua-engine'],
        $without
    ));
    $commonDependencies = array_values(array_intersect($dependencies, ['base']));
    $suiteDependencies = array_values(array_diff($dependencies, $commonDependencies));

    return implode("\n", [
        'common test-options',
        '  build-depends: ' . implode(', ', $commonDependencies),
        '',
        'test-suite test-pandoc-lua-engine',
        '  import: test-options',
        '  type: ' . $type,
        '  main-is: ' . ($mainIs ?? 'test-pandoc-lua-engine.hs'),
        '  hs-source-dirs: ' . ($sourceDirectory ?? 'pandoc-lua-engine/test'),
        '  build-depends:',
        '    ' . implode(",\n    ", $suiteDependencies),
    ]);
};

$requiredFiles = static function (string $project, ?string $pandocPackage = null, ?string $luaPackage = null) use ($pandocCabal, $luaCabal): array {
    return [
        'cabal.project' => $project,
        'pandoc.cabal' => $pandocPackage ?? $pandocCabal(),
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => $luaPackage ?? $luaCabal(),
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
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectPackages(), $audit['projectPackageClosure']['missingPackages']);
        $t->same(['embed_data_files', 'http'], $audit['projectPackageClosure']['missingFlags']['pandoc']);
        $t->same([
            'auto-update',
            'crypton',
            'skylighting-format-blaze-html',
            'skylighting-format-context',
        ], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same(['test:test-pandoc', 'test:test-pandoc-lua-engine'], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['nonMutatingPlan']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing required upstream runner files', $blocked);
        $t->contains('missing required Cabal toolchain commands: cabal', $blocked);
        $t->contains('missing cabal.project package entries', $blocked);
        $t->contains('missing cabal.project solver constraints', $blocked);
        $t->contains('missing Cabal runner test-suite stanzas', $blocked);
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
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectPackages(), $audit['projectPackageClosure']['presentPackages']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectConstraints(), $audit['projectConstraintClosure']['presentConstraints']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['projectConstraintClosure']['mismatchedConstraints']);
        $t->same(['test:test-pandoc', 'test:test-pandoc-lua-engine'], $audit['runnerTargets']);
        $t->same('pandoc.cabal', $audit['runnerEntryPoints']['test:test-pandoc']['packageFile']);
        $t->same('exitcode-stdio-1.0', $audit['runnerEntryPoints']['test:test-pandoc']['type']);
        $t->same('pandoc-lua-engine/pandoc-lua-engine.cabal', $audit['runnerEntryPoints']['test:test-pandoc-lua-engine']['packageFile']);
        $t->same('exitcode-stdio-1.0', $audit['runnerEntryPoints']['test:test-pandoc-lua-engine']['type']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['type']);
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['type']);
        $t->same(true, in_array('base', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(true, in_array('zip-archive', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(true, in_array('tasty-lua', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildDepends'], true));
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExecutableOptions()['test:test-pandoc'], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['ghcOptions']);
        $t->contains('non-mutating solver/build plan', $audit['activationGate']);
        $t->contains('record cabal.project package/flag closure', $audit['nonMutatingPlan'][0]);
        $t->contains('solver constraints and runner executable options', $audit['nonMutatingPlan'][1]);
        $t->contains('test-suite type, entry point, and direct build-depends closure', $audit['nonMutatingPlan'][2]);
    },
    'flags missing and mismatched cabal project git pins' => static function (TestRunner $t) use ($makeTree, $removeTree, $requiredFiles): void {
        $project = implode("\n", [
            'packages: . pandoc-lua-engine',
            '',
            'package pandoc',
            '  flags: +embed_data_files -http',
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
        $t->same(['pandoc-server', 'pandoc-cli'], $audit['projectPackageClosure']['missingPackages']);
        $t->same([
            'expected' => true,
            'actual' => false,
        ], $audit['projectPackageClosure']['mismatchedFlags']['pandoc']['http']);
        $t->same([
            'auto-update',
            'crypton',
            'skylighting-format-blaze-html',
            'skylighting-format-context',
        ], $audit['projectConstraintClosure']['missingConstraints']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing cabal.project source-repository pins', $blocked);
        $t->contains('mismatched cabal.project source-repository pins: doclayout', $blocked);
        $t->contains('missing cabal.project package entries: pandoc-server, pandoc-cli', $blocked);
        $t->contains('mismatched cabal.project package flags: pandoc:http expected +, found -', $blocked);
        $t->contains('missing cabal.project solver constraints', $blocked);
    },
    'rejects hydrated checkout with incomplete runner package closure' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal(['zip-archive', 'tasty-quickcheck'], 'wrong-main.hs', 'other-test'),
            $luaCabal(['tasty-lua', 'hslua'])
        ));
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->contains('main-is expected test-pandoc.hs, found wrong-main.hs', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][0]);
        $t->contains('hs-source-dirs missing test', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][1]);
        $t->same(['tasty-quickcheck', 'zip-archive'], $audit['runnerDependencyClosure']['missingDependencies']['test:test-pandoc']);
        $t->same(['hslua', 'tasty-lua'], $audit['runnerDependencyClosure']['missingDependencies']['test:test-pandoc-lua-engine']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner entry points', $blocked);
        $t->contains('missing Cabal runner direct build-depends', $blocked);
    },
    'blocks stale solver constraints and stripped runner executable options' => static function (TestRunner $t) use ($makeTree, $removeTree, $requiredFiles, $pandocCabal, $luaCabal): void {
        $project = implode("\n", [
            'packages: .',
            '  pandoc-lua-engine pandoc-server pandoc-cli',
            'constraints:',
            '  skylighting-format-blaze-html >= 0.1.1,',
            '  auto-update >= 0.2.6,',
            '',
            'package pandoc',
            '  flags: +embed_data_files +http',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/doclayout.git',
            '  tag: ef7f18308a61787244a80885d907fcd2c16604d4',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/typst-symbols.git',
            '  tag: 6e97668c9f2ffea09f3187c34b7641038370fd21',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/typst-hs.git',
            '  tag: 19e835d40663a92df5bed4e8a0fca5465cacdd6b',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/texmath.git',
            '  tag: 0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://github.com/jgm/citeproc.git',
            '  tag: 1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd',
        ]);
        $root = $makeTree($requiredFiles(
            $project,
            $pandocCabal([], null, null, '-rtsopts'),
            $luaCabal()
        ));
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([
            'crypton',
            'skylighting-format-context',
        ], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([
            'expected' => '>= 0.1.2',
            'actual' => '>= 0.1.1',
        ], $audit['projectConstraintClosure']['mismatchedConstraints']['skylighting-format-blaze-html']);
        $t->same([
            '-with-rtsopts=-A8m',
            '-threaded',
        ], $audit['runnerDependencyClosure']['missingExecutableOptions']['test:test-pandoc']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing cabal.project solver constraints: crypton, skylighting-format-context', $blocked);
        $t->contains('mismatched cabal.project solver constraints: skylighting-format-blaze-html expected >= 0.1.2, found >= 0.1.1', $blocked);
        $t->contains('missing Cabal runner executable options: test:test-pandoc (-with-rtsopts=-A8m, -threaded)', $blocked);
        $t->contains('package entries/flags/constraints', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'rejects non executable cabal runner test-suite types' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'detailed-0.9'),
            $luaCabal([], null, null, 'detailed-0.9')
        ));
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->contains('type expected exitcode-stdio-1.0, found detailed-0.9', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][0]);
        $t->contains('type expected exitcode-stdio-1.0, found detailed-0.9', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc-lua-engine'][0]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner entry points: test:test-pandoc (type expected exitcode-stdio-1.0, found detailed-0.9)', $blocked);
        $t->contains('exitcode-stdio test-suite types', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
];
