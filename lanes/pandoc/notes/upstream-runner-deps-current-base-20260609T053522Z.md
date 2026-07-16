# Upstream Runner Dependency Audit - Cabal Dry-Run Workspace

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T053522Z`
Accepted base: `43b1a4a1010b27f9642a54fbdd65b896e3bf9eec`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane
before editing.

## Source Truth

The lane manifest pins upstream Pandoc at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

This slice stays inside the native PHP upstream-runner dependency audit. It
does not execute Pandoc, Cabal solver/build/test commands, Haskell test
binaries, benchmark executables, Stack, Word, LibreOffice, zip/unzip, external
template engines, external converters, TeX/PDF engines, browser renderers,
online services, live provider tests, or live-service provider tests.

## Behavior

`UpstreamRunnerDependencyAudit` now tightens the descriptor-only Cabal dry-run
preflight before any non-mutating solver/build plan can be claimed:

- `runner-test-dependencies` uses `cabal v2-build --offline --project-dir=. --builddir=.port-libs/pandoc-runner/cabal-build/runner-test-dependencies --dry-run --only-dependencies --enable-tests --disable-benchmarks test:test-pandoc test:test-pandoc-lua-engine`.
- `benchmark-dependencies` uses `cabal v2-build --offline --project-dir=. --builddir=.port-libs/pandoc-runner/cabal-build/benchmark-dependencies --dry-run --only-dependencies --disable-tests --enable-benchmarks benchmark:benchmark-pandoc`.
- The audit payload now records a descriptor-only local workspace with
  `CABAL_DIR`, `CABAL_CONFIG`, `XDG_CACHE_HOME`, `XDG_STATE_HOME`, and `TMPDIR`
  set to repo-local `.port-libs/pandoc-runner/**` paths.
- The workspace plan records expected build directories, transcript paths, and
  optional `plan.json` paths while explicitly forbidding live process
  environment dumps.

This is audit metadata only. It does not create those directories and does not
run Cabal.

## Evidence

Accepted baseline from the previous focused audit slice:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2528 assertions, 0 failures
```

Red-first focused run after adding only the new expectations:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records descriptor only cabal dry-run command envelope before planning
Values are not identical
Expected: '.port-libs/pandoc-runner/cabal-build/runner-test-dependencies'
Actual: NULL
FAIL records local cabal dry-run workspace before any environment can be used
Call to undefined method PortLibs\Pandoc\UpstreamRunnerDependencyAudit::expectedCabalPlanWorkspace()
1 test files, 2522 assertions, 2 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2544 assertions, 0 failures
```

Focused upstream-runner dependency family run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2641 assertions, 0 failures
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
- `+16` focused assertions.
- `+1` mapped Cabal plan workspace audit case in `UPSTREAM_TEST_MANIFEST.json`.

Example smoke: not run, because this is an upstream-runner dependency audit-only
slice and no WordPress-visible example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal/source fixtures, the
lane-local upstream inventory, and the focused PHP TestRunner.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal dependency plan in a separate explicitly
authorized slice. That future plan should use the descriptor-only offline
repo-local workspace recorded here and must not print live process environment
values into lane evidence.

## Non-Overlap

This does not repeat accepted static audits for required source files, package
identity, setup hooks, package flags/files/native fields, project pins,
packages, flags, constraints, runner/benchmark dependencies, options, modules,
file artifact hashes, Lua-engine library closure, server library closure,
`pandoc-cli` executable semantics, conditional branches, Cabal plan stability,
benchmark fixture payload semantics, benchmark entry-source semantics,
benchmark UTF-8/error semantics, package/project source repository closure, or
the prior descriptor-only Cabal dry-run command targets.

The owned behavior is only the offline/project-dir/builddir isolation and
repo-local no-env-dump workspace descriptor for the already-audited runner and
benchmark dependency targets before any Cabal invocation.

## Follow-Up

Hydrate or verify the pinned Pandoc checkout and run the same native static
audit against real `pandoc.cabal`, `pandoc-cli`, `pandoc-lua-engine`,
`pandoc-server`, `cabal.project`, and `cabal.project.freeze` sources before any
explicitly authorized non-mutating Cabal plan. Keep Pandoc/Cabal/Haskell runner
and benchmark execution parked unless explicitly authorized.
