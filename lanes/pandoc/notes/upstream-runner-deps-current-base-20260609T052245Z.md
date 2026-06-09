# Upstream Runner Dependency Audit - Cabal Dry-Run Command Envelope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T052245Z`
Accepted base: `aeac7627505caef0c7f45b74c533b70ec36e1807`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane before editing.

## Source Truth

The lane manifest pins upstream Pandoc at `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
No hydrated Pandoc checkout or Pandoc Cabal package/project files were present under `/home/claude/port-libs/.upstream-cache` for this audit. `ghc 9.10.3` and `cabal 3.12.1.0` are on PATH; `stack` is absent.

This slice stays inside the native PHP upstream-runner dependency audit. It does not execute Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, zip/unzip, external converters, online services, live provider tests, or live-service provider tests.

## Behavior

`UpstreamRunnerDependencyAudit` now exports exact descriptor-only Cabal dry-run command envelopes before a non-mutating solver/build plan can be claimed:

- `runner-test-dependencies`: `cabal v2-build --dry-run --only-dependencies --enable-tests --disable-benchmarks test:test-pandoc test:test-pandoc-lua-engine`
- `benchmark-dependencies`: `cabal v2-build --dry-run --only-dependencies --disable-tests --enable-benchmarks benchmark:benchmark-pandoc`

The audit payload records the command arguments, target components, working-directory requirement, output-capture expectation, and explicit execution policy: `descriptor-only; do not execute from this isolated PHP lane`.

## Evidence

Baseline focused run before the new test:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2518 assertions, 0 failures
```

Red-first focused run after adding only the new test:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records descriptor only cabal dry-run command envelope before planning
Call to undefined method PortLibs\Pandoc\UpstreamRunnerDependencyAudit::expectedCabalPlanCommands()
1 test files, 2518 assertions, 1 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2528 assertions, 0 failures
```

Focused upstream-runner dependency family run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2625 assertions, 0 failures
```

Syntax and metadata checks:

```text
php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php
No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php

php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php

php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f . " OK" . PHP_EOL; }'
lanes/pandoc/lane-status.json OK
lanes/pandoc/UPSTREAM_TEST_MANIFEST.json OK

git diff --check -- lanes/pandoc
passed with no output
```

Delta:

- `+1` focused PHP PASS case.
- `+10` focused assertions.
- `+1` mapped upstream-runner dependency audit case in `UPSTREAM_TEST_MANIFEST.json`.

Example smoke: not run, because this is an upstream-runner dependency audit-only slice and no WordPress-visible example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses `UpstreamRunnerDependencyAudit`, lane-local Cabal/source fixtures, the lane-local upstream inventory, and the focused PHP TestRunner.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout and a reviewed non-mutating Cabal dependency plan before any Haskell executable build, benchmark executable, or runner execution is considered in a separate explicitly authorized slice.

## Non-Overlap

This does not repeat accepted static audits for required source files, package identity, setup hooks, package flags/files/native fields, project pins, packages, flags, constraints, runner/benchmark dependencies, options, modules, file artifact hashes, Lua-engine library closure, server library closure, `pandoc-cli` executable semantics, conditional branches, Cabal plan stability, benchmark fixture payload semantics, benchmark entry-source semantics, benchmark UTF-8/error semantics, or package/project source repository closure.

The owned behavior is only the descriptor-only Cabal dry-run command envelope for the already-audited runner and benchmark dependency targets before any Cabal invocation.

## Follow-Up

Hydrate or verify the pinned Pandoc checkout and run the same native static audit against real `pandoc.cabal`, `pandoc-cli`, `pandoc-lua-engine`, `pandoc-server`, `cabal.project`, and `cabal.project.freeze` sources before any explicitly authorized non-mutating Cabal plan. Keep Pandoc/Cabal/Haskell runner and benchmark execution parked unless explicitly authorized.
