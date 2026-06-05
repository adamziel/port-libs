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

$pandocCabal = static function (array $without = [], ?string $mainIs = null, ?string $sourceDirectory = null, ?string $ghcOptions = null, string $type = 'exitcode-stdio-1.0', ?string $buildable = null, ?string $defaultLanguage = 'Haskell2010'): string {
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

    $testSuite = [
        '',
        'test-suite test-pandoc',
        '  import: common-executable',
        '  type: ' . $type,
    ];
    if ($buildable !== null) {
        $testSuite[] = '  buildable: ' . $buildable;
    }
    $testSuite = array_merge($testSuite, [
        '  main-is: ' . ($mainIs ?? 'test-pandoc.hs'),
        '  hs-source-dirs: ' . ($sourceDirectory ?? 'test'),
        '  build-depends:',
        '    ' . implode(",\n    ", $suiteDependencies),
        '  other-modules:',
        '    ' . implode(",\n    ", UpstreamRunnerDependencyAudit::expectedRunnerOtherModules()['test:test-pandoc']),
    ]);

    return implode("\n", array_merge([
        'common common-options',
        '  build-depends: ' . implode(', ', $commonDependencies),
        $defaultLanguage === null || $defaultLanguage === '' ? '' : '  default-language: ' . $defaultLanguage,
        '',
    ], $commonExecutable, $testSuite));
};

$luaCabal = static function (array $without = [], ?string $mainIs = null, ?string $sourceDirectory = null, string $type = 'exitcode-stdio-1.0', ?string $buildable = null, ?string $defaultLanguage = 'Haskell2010', array $libraryWithout = []): string {
    $dependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedRunnerDependencies()['test:test-pandoc-lua-engine'],
        $without
    ));
    $commonDependencies = array_values(array_intersect($dependencies, ['base']));
    $suiteDependencies = array_values(array_diff($dependencies, $commonDependencies));
    $libraryDependencies = array_values(array_diff(
        UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDependencies(),
        $libraryWithout
    ));

    $stanzas = [
        'common test-options',
        '  build-depends: ' . implode(', ', $commonDependencies),
        $defaultLanguage === null || $defaultLanguage === '' ? '' : '  default-language: ' . $defaultLanguage,
        '',
        'library',
        '  import: test-options',
        '  build-depends:',
        '    ' . implode(",\n    ", $libraryDependencies),
        '',
        'test-suite test-pandoc-lua-engine',
        '  import: test-options',
        '  type: ' . $type,
    ];
    if ($buildable !== null) {
        $stanzas[] = '  buildable: ' . $buildable;
    }
    return implode("\n", array_merge($stanzas, [
        '  main-is: ' . ($mainIs ?? 'test-pandoc-lua-engine.hs'),
        '  hs-source-dirs: ' . ($sourceDirectory ?? 'pandoc-lua-engine/test'),
        '  build-depends:',
        '    ' . implode(",\n    ", $suiteDependencies),
        '  other-modules:',
        '    ' . implode(",\n    ", UpstreamRunnerDependencyAudit::expectedRunnerOtherModules()['test:test-pandoc-lua-engine']),
    ]));
};

$testPandocEntryPoint = static function (): string {
    return implode("\n", [
        'module Main (main) where',
        'import GHC.IO.Encoding',
        'import Test.Tasty',
        'import Text.Pandoc.App (convertWithOpts)',
        'import Text.Pandoc.Scripting (noEngine)',
        'import Text.Pandoc.Shared (inDirectory)',
        'import System.Environment (getArgs, getExecutablePath)',
        'import qualified Tests.Command',
        'import qualified Tests.Readers.Markdown',
        'import qualified Tests.Writers.Markdown',
        'import qualified Tests.Writers.Native',
        'main = do',
        '  setLocaleEncoding utf8',
        '  args <- getArgs',
        '  case args of',
        '    "--emulate":args\' -> convertWithOpts noEngine undefined',
        '    _ -> inDirectory "test" $ do',
        '      fp <- getExecutablePath',
        '      defaultMain $ tests fp',
        'tests fp = testGroup "pandoc tests" [Tests.Command.tests, Tests.Readers.Markdown.tests, Tests.Writers.Native.tests, Tests.Writers.Markdown.tests]',
    ]);
};

$luaEntryPoint = static function (): string {
    return implode("\n", [
        'module Main (main) where',
        'import Test.Tasty (TestTree, defaultMain, testGroup)',
        'import System.Directory (withCurrentDirectory)',
        'import qualified Tests.Lua',
        'import qualified Tests.Lua.Module',
        'import qualified Tests.Lua.Reader',
        'import qualified Tests.Lua.Writer',
        'main = withCurrentDirectory "test" $ defaultMain tests',
        'tests :: TestTree',
        'tests = testGroup "pandoc Lua engine" [ testGroup "Lua filters" Tests.Lua.tests, testGroup "Lua modules" Tests.Lua.Module.tests, testGroup "Custom writers" Tests.Lua.Writer.tests, testGroup "Custom readers" Tests.Lua.Reader.tests ]',
    ]);
};

$runnerArtifacts = static function (): array {
    $files = [];
    foreach (UpstreamRunnerDependencyAudit::expectedRunnerArtifacts() as $relativePath => $kind) {
        if ($kind === 'directory') {
            $files[$relativePath . '/.audit-keep'] = 'fixture root present';
        } else {
            $files[$relativePath] = 'fixture artifact present';
        }
    }

    return $files;
};

$requiredFiles = static function (string $project, ?string $pandocPackage = null, ?string $luaPackage = null, bool $includeRunnerArtifacts = true) use ($pandocCabal, $luaCabal, $runnerArtifacts, $testPandocEntryPoint, $luaEntryPoint): array {
    $files = [
        'cabal.project' => $project,
        'pandoc.cabal' => $pandocPackage ?? $pandocCabal(),
        'pandoc-lua-engine/pandoc-lua-engine.cabal' => $luaPackage ?? $luaCabal(),
        'test/test-pandoc.hs' => $testPandocEntryPoint(),
        'pandoc-lua-engine/test/test-pandoc-lua-engine.hs' => $luaEntryPoint(),
    ];

    if ($includeRunnerArtifacts) {
        $files = array_merge($files, $runnerArtifacts());
    }

    return $files;
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
        $t->same([], $audit['requiredFileProvenance']['present']);
        $t->same($audit['missingFiles'], $audit['requiredFileProvenance']['missing']);
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
        $t->same(array_keys(UpstreamRunnerDependencyAudit::expectedRunnerArtifacts()), $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['present']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $t->same([], $audit['nonMutatingPlan']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing required upstream runner files', $blocked);
        $t->contains('missing required Cabal toolchain commands: cabal', $blocked);
        $t->contains('missing cabal.project package entries', $blocked);
        $t->contains('missing cabal.project solver constraints', $blocked);
        $t->contains('missing Cabal runner test-suite stanzas', $blocked);
        $t->contains('missing upstream runner source/golden fixture artifacts', $blocked);
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
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same(UpstreamRunnerDependencyAudit::expectedLuaEngineLibraryDependencies(), $audit['luaEngineLibraryClosure']['expectedDependencies']);
        $t->same([], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same(true, in_array('hslua-module-zip', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $t->same(true, in_array('pandoc-lua-marshal', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['type']);
        $t->same('exitcode-stdio-1.0', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['type']);
        $t->same('Haskell2010', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['defaultLanguage']);
        $t->same('Haskell2010', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['defaultLanguage']);
        $t->same(true, in_array('base', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(true, in_array('zip-archive', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildDepends'], true));
        $t->same(true, in_array('tasty-lua', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildDepends'], true));
        $t->same(true, in_array('Tests.Command', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherModules'], true));
        $t->same(true, in_array('Tests.Writers.Native', $audit['runnerDependencyClosure']['present']['test:test-pandoc']['otherModules'], true));
        $t->same(true, in_array('Tests.Lua.Reader', $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['otherModules'], true));
        $t->same(UpstreamRunnerDependencyAudit::expectedRunnerExecutableOptions()['test:test-pandoc'], $audit['runnerDependencyClosure']['present']['test:test-pandoc']['ghcOptions']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingTargets']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingSemantics']);
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedRunnerEntrySourceSemantics()['test:test-pandoc']['requiredSnippets']),
            $audit['runnerEntrySourceClosure']['present']['test:test-pandoc']['matchedSnippets']
        );
        $t->same(
            array_keys(UpstreamRunnerDependencyAudit::expectedRunnerEntrySourceSemantics()['test:test-pandoc-lua-engine']['requiredSnippets']),
            $audit['runnerEntrySourceClosure']['present']['test:test-pandoc-lua-engine']['matchedSnippets']
        );
        $t->same(array_keys(UpstreamRunnerDependencyAudit::expectedRunnerArtifacts()), $audit['runnerArtifactClosure']['present']);
        $t->same([], $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $t->contains('non-mutating solver/build plan', $audit['activationGate']);
        $t->contains('record cabal.project package/flag closure', $audit['nonMutatingPlan'][0]);
        $t->contains('runner entry-point semantics', $audit['nonMutatingPlan'][0]);
        $t->contains('solver constraints and runner executable options', $audit['nonMutatingPlan'][1]);
        $t->contains('test-suite type, buildable state, default-language, entry point, direct build-depends, and other-modules closure', $audit['nonMutatingPlan'][2]);
        $t->contains('pandoc-lua-engine library HsLua module dependency closure', $audit['nonMutatingPlan'][2]);
    },
    'records required runner file provenance before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => ['available' => true, 'version' => '9.10.3'],
                'cabal' => ['available' => true, 'version' => '3.12.1.0'],
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([
            'cabal.project',
            'pandoc.cabal',
            'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'test/test-pandoc.hs',
            'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
        ], $audit['requiredFileProvenance']['expected']);
        $t->same([], $audit['requiredFileProvenance']['missing']);
        foreach ($audit['requiredFileProvenance']['expected'] as $relativePath) {
            $t->same(hash('sha256', $files[$relativePath]), $audit['requiredFileProvenance']['present'][$relativePath]['sha256']);
            $t->same(strlen($files[$relativePath]), $audit['requiredFileProvenance']['present'][$relativePath]['bytes']);
        }
        $t->contains('package-file hashes', $audit['nonMutatingPlan'][0]);
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
    'blocks source repository package type or location drift with matching tags' => static function (TestRunner $t) use ($makeTree, $removeTree, $requiredFiles, $pandocCabal, $luaCabal): void {
        $project = implode("\n", [
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
            'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1',
            '',
            'package pandoc',
            '  flags: +embed_data_files +http',
            '',
            'source-repository-package',
            '  type: svn',
            '  location: https://github.com/jgm/doclayout.git',
            '  tag: ef7f18308a61787244a80885d907fcd2c16604d4',
            '',
            'source-repository-package',
            '  type: git',
            '  location: https://mirror.example.invalid/jgm/typst-symbols.git',
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
            $pandocCabal(),
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
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'expected' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/doclayout.git',
            ],
            'actual' => [
                'type' => 'svn',
                'location' => 'https://github.com/jgm/doclayout.git',
            ],
        ], $audit['projectSourceRepositoryClosure']['mismatched']['doclayout']);
        $t->same([
            'expected' => [
                'type' => 'git',
                'location' => 'https://github.com/jgm/typst-symbols.git',
            ],
            'actual' => [
                'type' => 'git',
                'location' => 'https://mirror.example.invalid/jgm/typst-symbols.git',
            ],
        ], $audit['projectSourceRepositoryClosure']['mismatched']['typst-symbols']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched cabal.project source-repository package locations/types: doclayout, typst-symbols', $blocked);
        $t->contains('exact cabal.project source-repository Git types and locations', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
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
    'rejects non buildable cabal runner test-suites before solver planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'exitcode-stdio-1.0', 'False'),
            $luaCabal([], null, null, 'exitcode-stdio-1.0', 'False')
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
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same(false, $audit['runnerDependencyClosure']['present']['test:test-pandoc']['buildable']);
        $t->same(false, $audit['runnerDependencyClosure']['present']['test:test-pandoc-lua-engine']['buildable']);
        $t->contains('buildable expected true, found false', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc'][0]);
        $t->contains('buildable expected true, found false', $audit['runnerDependencyClosure']['mismatchedEntryPoints']['test:test-pandoc-lua-engine'][0]);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner entry points: test:test-pandoc (buildable expected true, found false)', $blocked);
        $t->contains('buildable exitcode-stdio test-suite types', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'rejects hydrated runner package closure without source and golden fixture artifacts' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $root = $makeTree($requiredFiles($pinnedProject(), null, null, false));
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
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $expectedMissing = array_values(array_diff(
            array_keys(UpstreamRunnerDependencyAudit::expectedRunnerArtifacts()),
            ['pandoc-lua-engine/test']
        ));
        $t->same($expectedMissing, $audit['runnerArtifactClosure']['missing']);
        $t->same(['pandoc-lua-engine/test'], $audit['runnerArtifactClosure']['present']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing upstream runner source/golden fixture artifacts', $blocked);
        $t->contains('runner source/golden fixtures', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua runner other-module source artifact drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        unset($files['pandoc-lua-engine/test/Tests/Lua/Reader.hs']);

        $root = $makeTree($files);
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
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same(['pandoc-lua-engine/test/Tests/Lua/Reader.hs'], $audit['runnerArtifactClosure']['missing']);
        $t->same([], $audit['runnerArtifactClosure']['wrongType']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing upstream runner source/golden fixture artifacts: pandoc-lua-engine/test/Tests/Lua/Reader.hs', $blocked);
        $t->contains('runner source/golden fixtures', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner entry point source semantic drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['test/test-pandoc.hs'] = implode("\n", [
            'module Main (main) where',
            'import Test.Tasty (defaultMain)',
            'main = defaultMain tests',
        ]);
        $files['pandoc-lua-engine/test/test-pandoc-lua-engine.hs'] = implode("\n", [
            'module Main (main) where',
            'import Test.Tasty (defaultMain)',
            'main = defaultMain tests',
        ]);

        $root = $makeTree($files);
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
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerEntrySourceClosure']['missingTargets']);
        $t->contains('sets locale encoding to utf8', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']));
        $t->contains('offers --emulate command runner path', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']));
        $t->contains('loads markdown writer tests', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc']));
        $t->contains('runs from lua engine test directory', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc-lua-engine']));
        $t->contains('names lua engine tasty group', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc-lua-engine']));
        $t->contains('loads custom reader tests', implode("\n", $audit['runnerEntrySourceClosure']['missingSemantics']['test:test-pandoc-lua-engine']));
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing runner entry point source semantics', $blocked);
        $t->contains('runner entry-point source semantics', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner other-modules drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Command,\n",
            '',
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Writers.Native",
            '    Tests.Writers.HTML',
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "    Tests.Lua.Reader,\n",
            '',
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );

        $root = $makeTree($files);
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
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same(['Tests.Command', 'Tests.Writers.Native'], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc']);
        $t->same(['Tests.Lua.Reader'], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc-lua-engine']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal runner other-modules', $blocked);
        $t->contains('runner other-modules closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks full pandoc runner reader and writer module closure drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Readers.Docx,\n",
            '',
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Writers.BBCode",
            '    Tests.Writers.Native',
            $files['pandoc.cabal']
        );

        $root = $makeTree($files);
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
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'Tests.Readers.Docx',
            'Tests.Writers.BBCode',
        ], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal runner other-modules: test:test-pandoc (Tests.Readers.Docx, Tests.Writers.BBCode)', $blocked);
        $t->contains('runner other-modules closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks lua engine library dependency drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            null,
            $luaCabal([], null, null, 'exitcode-stdio-1.0', null, 'Haskell2010', [
                'hslua-module-doclayout',
                'hslua-module-zip',
                'pandoc-lua-marshal',
            ])
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
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([
            'hslua-module-doclayout',
            'hslua-module-zip',
            'pandoc-lua-marshal',
        ], $audit['luaEngineLibraryClosure']['missingDependencies']);
        $t->same(true, in_array('hslua-module-path', $audit['luaEngineLibraryClosure']['presentDependencies'], true));
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing pandoc-lua-engine library build-depends: hslua-module-doclayout, hslua-module-zip, pandoc-lua-marshal', $blocked);
        $t->contains('pandoc-lua-engine library HsLua module dependency closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'blocks runner default-language drift before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles, $pandocCabal, $luaCabal): void {
        $root = $makeTree($requiredFiles(
            $pinnedProject(),
            $pandocCabal([], null, null, null, 'exitcode-stdio-1.0', null, 'Haskell98'),
            $luaCabal([], null, null, 'exitcode-stdio-1.0', null, '')
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
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['runnerDependencyClosure']['missingTargets']);
        $t->same([], $audit['runnerDependencyClosure']['mismatchedEntryPoints']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => 'Haskell98',
        ], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']['test:test-pandoc']);
        $t->same([
            'expected' => 'Haskell2010',
            'actual' => null,
        ], $audit['runnerDependencyClosure']['mismatchedDefaultLanguages']['test:test-pandoc-lua-engine']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('mismatched Cabal runner default-language', $blocked);
        $t->contains('test:test-pandoc expected Haskell2010, found Haskell98', $blocked);
        $t->contains('test:test-pandoc-lua-engine expected Haskell2010, found none', $blocked);
        $t->contains('Haskell2010 default-language closure', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'normalizes cabal line comments before resolving runner fields' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            "    Diff,\n    Glob,",
            "    Diff,\n    -- comment must not swallow the following dependency\n    Glob,",
            $files['pandoc.cabal']
        );
        $files['pandoc-lua-engine/pandoc-lua-engine.cabal'] = str_replace(
            "    bytestring,\n    directory,",
            "    bytestring,\n    -- comment must not hide directory from the parser\n    directory,",
            $files['pandoc-lua-engine/pandoc-lua-engine.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same([], $audit['runnerDependencyClosure']['missingDependencies']);
        $t->same([], $audit['runnerDependencyClosure']['missingExecutableOptions']);
        $t->same([], $audit['runnerDependencyClosure']['missingOtherModules']);
        $t->same([], $audit['blockedReasons']);
    },
    'does not count commented runner fields as present before cabal planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $files = $requiredFiles($pinnedProject());
        $files['pandoc.cabal'] = str_replace(
            '  ghc-options: -rtsopts -with-rtsopts=-A8m -threaded',
            "  ghc-options: -rtsopts -with-rtsopts=-A8m\n  -- -threaded is intentionally commented out",
            $files['pandoc.cabal']
        );
        $files['pandoc.cabal'] = str_replace(
            "    Tests.Command,\n",
            "    -- Tests.Command is intentionally commented out\n",
            $files['pandoc.cabal']
        );

        $root = $makeTree($files);
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(false, $audit['readyForNonMutatingCabalPlan']);
        $t->same(['-threaded'], $audit['runnerDependencyClosure']['missingExecutableOptions']['test:test-pandoc']);
        $t->same(['Tests.Command'], $audit['runnerDependencyClosure']['missingOtherModules']['test:test-pandoc']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing Cabal runner executable options: test:test-pandoc (-threaded)', $blocked);
        $t->contains('missing Cabal runner other-modules: test:test-pandoc (Tests.Command)', $blocked);
        $t->same([], $audit['nonMutatingPlan']);
    },
    'normalizes cabal project line comments before dependency planning' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = $pinnedProject();
        $project = str_replace(
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
            "packages: -- runner packages\n  . -- main pandoc package\n  pandoc-lua-engine\n  pandoc-server\n  pandoc-cli -- command package",
            $project
        );
        $project = str_replace(
            'constraints: skylighting-format-blaze-html >= 0.1.2, skylighting-format-context >= 0.1.0.2, auto-update >= 0.2.6, crypton >= 1.1.1',
            "constraints: -- solver floors\n  skylighting-format-blaze-html >= 0.1.2, -- HTML format floor\n  skylighting-format-context >= 0.1.0.2,\n  auto-update >= 0.2.6,\n  crypton >= 1.1.1 -- crypton floor",
            $project
        );
        $project = str_replace(
            '  flags: +embed_data_files +http',
            "  flags: +embed_data_files -- package data files\n  flags: +http -- HTTP reader support",
            $project
        );
        $project = str_replace('  type: git', '  type: git -- pinned Git dependency', $project);
        $project = preg_replace('/(  location: https:\/\/github\.com\/jgm\/[A-Za-z0-9_-]+\.git)/', '$1 -- upstream location', $project) ?? $project;
        $project = preg_replace('/(  tag: [a-f0-9]+)/', '$1 -- pinned commit', $project) ?? $project;

        $root = $makeTree($requiredFiles($project));
        try {
            $audit = UpstreamRunnerDependencyAudit::auditCheckout($root, [
                'ghc' => '9.10.3',
                'cabal' => '3.12.1.0',
            ]);
        } finally {
            $removeTree($root);
        }

        $t->same(true, $audit['readyForNonMutatingCabalPlan']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectPackages(), $audit['projectPackageClosure']['presentPackages']);
        $t->same([], $audit['projectPackageClosure']['missingPackages']);
        $t->same([], $audit['projectPackageClosure']['missingFlags']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $t->same(UpstreamRunnerDependencyAudit::expectedProjectConstraints(), $audit['projectConstraintClosure']['presentConstraints']);
        $t->same([], $audit['projectConstraintClosure']['missingConstraints']);
        $t->same([], $audit['projectConstraintClosure']['mismatchedConstraints']);
        $expectedPins = UpstreamRunnerDependencyAudit::expectedProjectPins();
        ksort($expectedPins);
        $t->same($expectedPins, $audit['projectSourceRepositoryPins']['present']);
        $t->same([], $audit['projectSourceRepositoryPins']['missing']);
        $t->same([], $audit['projectSourceRepositoryPins']['mismatched']);
        $t->same([], $audit['projectSourceRepositoryClosure']['missing']);
        $t->same([], $audit['projectSourceRepositoryClosure']['mismatched']);
        $t->same([], $audit['blockedReasons']);
    },
    'does not count commented cabal project packages or flags as present' => static function (TestRunner $t) use ($makeTree, $removeTree, $pinnedProject, $requiredFiles): void {
        $project = $pinnedProject();
        $project = str_replace(
            'packages: . pandoc-lua-engine pandoc-server pandoc-cli',
            "packages: . pandoc-lua-engine pandoc-server\n  -- pandoc-cli is intentionally commented out",
            $project
        );
        $project = str_replace(
            '  flags: +embed_data_files +http',
            '  flags: +embed_data_files -- +http is intentionally commented out',
            $project
        );

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
        $t->same(['pandoc-cli'], $audit['projectPackageClosure']['missingPackages']);
        $t->same(['http'], $audit['projectPackageClosure']['missingFlags']['pandoc']);
        $t->same([], $audit['projectPackageClosure']['mismatchedFlags']);
        $blocked = implode("\n", $audit['blockedReasons']);
        $t->contains('missing cabal.project package entries: pandoc-cli', $blocked);
        $t->contains('missing cabal.project package flags: pandoc (http)', $blocked);
        $t->contains('cabal.project package entries/flags/constraints', $audit['activationGate']);
        $t->same([], $audit['nonMutatingPlan']);
    },
];
