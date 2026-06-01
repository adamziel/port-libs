<?php

declare(strict_types=1);

use PortLibs\LightningCSS\UpstreamRunnerEvidence;

$rustMediaOutput = <<<'TEXT'
    Finished `test` profile [unoptimized + debuginfo] target(s) in 0.33s
     Running unittests src/lib.rs (/home/claude/port-libs/.upstream-cache/lightningcss/target/debug/deps/lightningcss-1d924f1c6120a833)

running 1 test
test tests::test_media ... ok

test result: ok. 1 passed; 0 failed; 0 ignored; 0 measured; 118 filtered out; finished in 0.04s
TEXT;

$nodeUvuOutput = <<<'TEXT'
node:internal/modules/package_json_reader:314
  throw new ERR_MODULE_NOT_FOUND(packageName, fileURLToPath(base), null);
        ^

Error [ERR_MODULE_NOT_FOUND]: Cannot find package 'uvu' imported from /home/claude/port-libs/.upstream-cache/lightningcss/node/test/transform.test.mjs
TEXT;

$nodeIndexOutput = <<<'TEXT'
MODULE_NOT_FOUND
Cannot find module 'detect-libc'
TEXT;

$wasmOutput = <<<'TEXT'
Error: Cannot find module 'napi-wasm'
Require stack:
- /home/claude/port-libs/.upstream-cache/lightningcss/wasm/wasm-node.cjs
TEXT;

return [
    'upstream runner evidence records bounded rust media test pass' => static function (TestRunner $t) use ($rustMediaOutput): void {
        $record = UpstreamRunnerEvidence::rustCargoTest(
            'Rust lib tests::test_media',
            'cargo test --manifest-path /home/claude/port-libs/.upstream-cache/lightningcss/Cargo.toml --lib tests::test_media -- --exact',
            0,
            $rustMediaOutput,
            true
        );

        $t->same('rust', $record['runner']);
        $t->same('passed', $record['status']);
        $t->same(0, $record['exitCode']);
        $t->same(1, $record['passed']);
        $t->same(0, $record['failed']);
        $t->same(118, $record['filtered']);
        $t->true($record['dirtyCache']);
        $t->contains('tests::test_media ... ok', $record['output']);
    },
    'upstream runner evidence records native node addon smoke pass' => static function (TestRunner $t): void {
        $record = UpstreamRunnerEvidence::nodeNativeAddonSmoke(
            'Node direct native addon transform smoke',
            'node -e require lightningcss.linux-x64-gnu.node transform probe',
            0,
            ".foo{color:red}\n",
            '.foo{color:red}'
        );

        $t->same('node-native', $record['runner']);
        $t->same('passed', $record['status']);
        $t->same(0, $record['exitCode']);
        $t->same('.foo{color:red}', $record['actual']);
        $t->same('.foo{color:red}', $record['expected']);
    },
    'upstream runner evidence records node and wasm dependency blockers' => static function (TestRunner $t) use ($nodeUvuOutput, $nodeIndexOutput, $wasmOutput): void {
        $uvu = UpstreamRunnerEvidence::moduleDependencyBlocker(
            'node',
            'Node uvu transform runner',
            'node /home/claude/port-libs/.upstream-cache/lightningcss/node/test/transform.test.mjs',
            1,
            $nodeUvuOutput
        );
        $detectLibc = UpstreamRunnerEvidence::moduleDependencyBlocker(
            'node',
            'Node package entrypoint load',
            'node -e require node/index.js',
            1,
            $nodeIndexOutput
        );
        $wasm = UpstreamRunnerEvidence::moduleDependencyBlocker(
            'wasm',
            'WASM node runtime smoke',
            'node -e require wasm/wasm-node.cjs',
            1,
            $wasmOutput
        );

        $t->same('blocked', $uvu['status']);
        $t->same('uvu', $uvu['dependency']);
        $t->same('missing Node module uvu', $uvu['blocker']);
        $t->same('blocked', $detectLibc['status']);
        $t->same('detect-libc', $detectLibc['dependency']);
        $t->same('missing Node module detect-libc', $detectLibc['blocker']);
        $t->same('blocked', $wasm['status']);
        $t->same('napi-wasm', $wasm['dependency']);
        $t->same('missing Node module napi-wasm', $wasm['blocker']);
    },
    'upstream runner evidence records wasm build command blocker' => static function (TestRunner $t): void {
        $record = UpstreamRunnerEvidence::missingCommandBlocker(
            'wasm',
            'WASM release build preflight',
            'wasm-opt --version',
            127,
            '/bin/bash: line 1: wasm-opt: command not found'
        );

        $t->same('blocked', $record['status']);
        $t->same('wasm-opt', $record['tool']);
        $t->same('missing command wasm-opt', $record['blocker']);
    },
    'upstream runner evidence summarizes partial runner closure' => static function (TestRunner $t) use ($rustMediaOutput, $nodeUvuOutput, $nodeIndexOutput, $wasmOutput): void {
        $records = [
            UpstreamRunnerEvidence::rustCargoTest('Rust lib tests::test_media', 'cargo test --lib tests::test_media -- --exact', 0, $rustMediaOutput, true),
            UpstreamRunnerEvidence::nodeNativeAddonSmoke('Node direct native addon transform smoke', 'node native smoke', 0, '.foo{color:red}', '.foo{color:red}'),
            UpstreamRunnerEvidence::moduleDependencyBlocker('node', 'Node uvu transform runner', 'node node/test/transform.test.mjs', 1, $nodeUvuOutput),
            UpstreamRunnerEvidence::moduleDependencyBlocker('node', 'Node package entrypoint load', 'node -e require node/index.js', 1, $nodeIndexOutput),
            UpstreamRunnerEvidence::moduleDependencyBlocker('wasm', 'WASM node runtime smoke', 'node -e require wasm/wasm-node.cjs', 1, $wasmOutput),
            UpstreamRunnerEvidence::missingCommandBlocker('wasm', 'WASM release build preflight', 'wasm-opt --version', 127, '/bin/bash: line 1: wasm-opt: command not found'),
        ];

        $summary = UpstreamRunnerEvidence::summarize($records);

        $t->same(2, $summary['passed']);
        $t->same(4, $summary['blocked']);
        $t->same(0, $summary['failed']);
        $t->same('partial', $summary['fullRunnerStatus']);
        $t->same([
            'missing Node module uvu',
            'missing Node module detect-libc',
            'missing Node module napi-wasm',
            'missing command wasm-opt',
        ], $summary['blockers']);
        $t->same([
            'Rust lib tests::test_media',
            'Node direct native addon transform smoke',
        ], $summary['passedLabels']);
        $t->contains('runner closure needs upstream dependency installation', $summary['dependencyClosure']);
    },
];
