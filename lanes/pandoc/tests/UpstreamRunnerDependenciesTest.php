<?php

declare(strict_types=1);

use PortLibs\Pandoc\UpstreamRunnerDependencies;

return [
    'reports blocked pandoc runner gate when upstream cabal files are absent' => static function (TestRunner $t): void {
        $audit = new UpstreamRunnerDependencies();
        $gate = $audit->evaluateLocalGate([], [
            'ghc' => '9.10.3',
            'cabal' => '3.12.1.0',
            'stack' => null,
        ]);

        $t->same('pandoc-upstream-runner-dependencies', $gate['kind']);
        $t->same('0640c4c9859aa5a3ede082c190fcd5883c24ac83', $gate['upstreamCommit']);
        $t->same(false, $gate['willExecute']);
        $t->same('blocked-missing-upstream-checkout', $gate['status']);
        $t->same(false, $gate['activationReady']);
        $t->same([
            'cabal.project',
            'pandoc.cabal',
            'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'pandoc-server/pandoc-server.cabal',
            'pandoc-cli/pandoc-cli.cabal',
            'test/test-pandoc.hs',
            'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
            'benchmark/benchmark-pandoc.hs',
        ], $gate['missingFiles']);
        $t->same(['test:test-pandoc', 'test:test-pandoc-lua-engine', 'benchmark:benchmark-pandoc'], $gate['solverTargets']);
        $t->same('no-new-native-support-component', $gate['dependencyBacklogDecision']);
        $t->contains('pandoc-runner-not-executed', implode(',', $gate['diagnostics']));
        $t->contains('cabal-build-not-run', implode(',', $gate['diagnostics']));
        $t->contains('haskell-test-binaries-not-run', implode(',', $gate['diagnostics']));
        $t->contains('cabal-available:3.12.1.0', implode(',', $gate['diagnostics']));
        $t->contains('ghc-available:9.10.3', implode(',', $gate['diagnostics']));
        $t->contains('stack-not-on-path', implode(',', $gate['diagnostics']));
        $t->contains('missing-required-upstream-files:8', implode(',', $gate['diagnostics']));
        $t->same('hydrate Pandoc upstream checkout at 0640c4c9859aa5a3ede082c190fcd5883c24ac83', $gate['activationGate'][0]);
        $t->contains('record a non-mutating Cabal solver/build plan', implode("\n", $gate['activationGate']));
    },

    'records pinned test pandoc and lua engine dependency closure' => static function (TestRunner $t): void {
        $audit = new UpstreamRunnerDependencies();
        $suites = $audit->testSuites();

        $t->same(['test-pandoc', 'test-pandoc-lua-engine'], array_keys($suites));
        $t->same('pandoc.cabal', $suites['test-pandoc']['packageFile']);
        $t->same('test-pandoc.hs', $suites['test-pandoc']['mainIs']);
        $t->same('utf-8', $suites['test-pandoc']['entryPoint']['setsLocaleEncoding']);
        $t->same('convertWithOpts noEngine command runner', $suites['test-pandoc']['entryPoint']['emulateMode']);
        $t->same(20, count($suites['test-pandoc']['dependencies']));
        $t->same(['name' => 'pandoc', 'kind' => 'local-library', 'constraint' => null], $suites['test-pandoc']['dependencies'][0]);
        $t->same(['name' => 'tasty-golden', 'constraint' => '>= 2.3 && < 2.4'], $suites['test-pandoc']['dependencies'][12]);
        $t->same(['name' => 'zip-archive', 'constraint' => '>= 0.4.3 && < 0.5'], $suites['test-pandoc']['dependencies'][19]);
        $t->contains('reader groups', implode(',', $suites['test-pandoc']['groups']));

        $t->same('pandoc-lua-engine/pandoc-lua-engine.cabal', $suites['test-pandoc-lua-engine']['packageFile']);
        $t->same('pandoc-lua-engine/test', $suites['test-pandoc-lua-engine']['entryPoint']['workingDirectory']);
        $t->same(14, count($suites['test-pandoc-lua-engine']['dependencies']));
        $t->same(['name' => 'hslua', 'constraint' => '>= 2.5 && < 2.6'], $suites['test-pandoc-lua-engine']['dependencies'][6]);
        $t->same(['name' => 'tasty-lua', 'constraint' => '>= 1.1 && < 1.2'], $suites['test-pandoc-lua-engine']['dependencies'][12]);
        $t->contains('pandoc-lua-marshal', implode(',', $suites['test-pandoc-lua-engine']['libraryExtraDependencies']));
        $t->contains('custom readers', implode(',', $suites['test-pandoc-lua-engine']['groups']));
    },

    'records cabal project source pins and existing support component decision' => static function (TestRunner $t): void {
        $audit = new UpstreamRunnerDependencies();
        $project = $audit->cabalProject();
        $pins = $audit->sourceRepositoryPins();
        $support = $audit->supportComponents();
        $summary = $audit->summary();

        $t->same(['.', 'pandoc-lua-engine', 'pandoc-server', 'pandoc-cli'], $project['packages']);
        $t->same(['embed_data_files', 'http'], $project['pandocFlags']);
        $t->same([
            'doclayout',
            'typst-symbols',
            'typst-hs',
            'texmath',
            'citeproc',
        ], array_keys($pins));
        $t->same('ef7f18308a61787244a80885d907fcd2c16604d4', $pins['doclayout']['commit']);
        $t->same('6e97668c9f2ffea09f3187c34b7641038370fd21', $pins['typst-symbols']['commit']);
        $t->same('19e835d40663a92df5bed4e8a0fca5465cacdd6b', $pins['typst-hs']['commit']);
        $t->same('0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a', $pins['texmath']['commit']);
        $t->same('1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd', $pins['citeproc']['commit']);
        $t->contains('pandoc-shared-zip-package-core', implode(',', $support));
        $t->contains('pandoc-pdf-engine-handoff-core', implode(',', $support));
        $t->contains('pandoc-media-bag-core', implode(',', $support));
        $t->same(18, count($support));
        $t->same(5, $summary['projectPinCount']);
        $t->same(18, $summary['supportComponentCount']);
        $t->same(8, $summary['requiredFileCount']);
        $t->same(3, $summary['solverTargetCount']);
        $t->same(2, $summary['testSuiteCount']);
        $t->same(20, $summary['testPandocDependencyCount']);
        $t->same(14, $summary['luaEngineTestDependencyCount']);
    },

    'marks runner dependency plan ready only after required cabal files are present' => static function (TestRunner $t): void {
        $audit = new UpstreamRunnerDependencies();
        $required = $audit->requiredFiles();
        $gate = $audit->evaluateLocalGate([
            './cabal.project',
            'pandoc.cabal',
            'pandoc-lua-engine//pandoc-lua-engine.cabal',
            'pandoc-server/pandoc-server.cabal',
            'pandoc-cli/pandoc-cli.cabal',
            'test/test-pandoc.hs',
            '/pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
            'benchmark/benchmark-pandoc.hs',
        ]);
        $missingOne = $audit->evaluateLocalGate(array_slice($required, 0, 7));

        $t->same('plan-ready', $gate['status']);
        $t->same(true, $gate['activationReady']);
        $t->same([], $gate['missingFiles']);
        $t->same($required, $gate['requiredFiles']);
        $t->same($required, $gate['presentFiles']);
        $t->contains('non-mutating-plan-ready', implode(',', $gate['diagnostics']));
        $t->contains('record non-mutating Cabal solver/build plan', implode("\n", $gate['activationGate']));
        $t->contains('benchmark:benchmark-pandoc', implode("\n", $gate['activationGate']));
        $t->contains('resolve project-pinned Git source-repository packages', implode("\n", $gate['activationGate']));

        $t->same('blocked-missing-upstream-checkout', $missingOne['status']);
        $t->same(['benchmark/benchmark-pandoc.hs'], $missingOne['missingFiles']);
    },

    'keeps lightweight runner gate blocked without server cli and benchmark closure files' => static function (TestRunner $t): void {
        $audit = new UpstreamRunnerDependencies();
        $gate = $audit->evaluateLocalGate([
            'cabal.project',
            'pandoc.cabal',
            'pandoc-lua-engine/pandoc-lua-engine.cabal',
            'test/test-pandoc.hs',
            'pandoc-lua-engine/test/test-pandoc-lua-engine.hs',
        ]);

        $t->same('blocked-missing-upstream-checkout', $gate['status']);
        $t->same(false, $gate['activationReady']);
        $t->same([
            'pandoc-server/pandoc-server.cabal',
            'pandoc-cli/pandoc-cli.cabal',
            'benchmark/benchmark-pandoc.hs',
        ], $gate['missingFiles']);
        $t->contains('missing-required-upstream-files:3', implode(',', $gate['diagnostics']));
        $t->contains('pandoc-server, pandoc-cli, and benchmark package entry files', implode("\n", $gate['activationGate']));
    },
];
