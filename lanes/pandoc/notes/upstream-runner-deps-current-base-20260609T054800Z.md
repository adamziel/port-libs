# Upstream Runner Dependency Audit - Package Stanza Fields

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T054800Z`
Accepted base: `2c84ca27878846c6b3725d422a6af783d4bbe9c7`

## Rework Notes

No current `port-pandoc-*.needs-lane-rework.md` note was present for this lane
before editing.

## Source Truth

The lane manifest pins upstream Pandoc at
`jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

The hydrated upstream checkout is still not present in the local upstream cache.
Static cache discovery found no Pandoc Cabal package/project files under
`/home/claude/port-libs/.upstream-cache`, so this slice stays inside the native
PHP audit fixture and does not claim runner parity.

This slice does not execute Pandoc, Cabal solver/build/test commands, Haskell
test binaries, benchmark executables, Stack, Word, LibreOffice, zip/unzip,
external template engines, external converters, TeX/PDF engines, browser
renderers, online services, live provider tests, or live-service provider
tests.

## Behavior

`UpstreamRunnerDependencyAudit` now parses unconditional `cabal.project`
`package <name>` stanza fields, records the normalized present field map, and
expects only `flags` under the pinned `package pandoc` stanza before a
non-mutating Cabal plan can be considered ready.

Unexpected package stanza fields now block planning with explicit diagnostics.
The focused fixture covers `constraints`, `ghc-options`, and `tests` added under
`package pandoc`; all are reported under
`projectPackageClosure.unexpectedPackageFields` while the existing package and
flag closure remains clean.

The activation gate and non-mutating plan wording were extended to preserve the
existing package/flag closure contract while adding the new package stanza field
closure requirement.

## Evidence

Baseline focused upstream-runner dependency family on accepted base:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2641 assertions, 0 failures
```

Final focused run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 2558 assertions, 0 failures
```

Final focused upstream-runner dependency family run:

```text
php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 2655 assertions, 0 failures
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
- `+14` focused assertions.
- `+1` mapped Cabal project package stanza field audit case in
  `UPSTREAM_TEST_MANIFEST.json`.

Example smoke: not run, because this is an upstream-runner dependency audit-only
slice and no WordPress-visible example was added or changed.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal/project fixtures, the
lane-local upstream inventory, and the focused PHP TestRunner.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal dependency plan in a separate explicitly
authorized slice.

## Non-Overlap

This does not repeat accepted static audits for required source files, package
identity, setup hooks, package flags/files/native fields, project pins,
packages, flags, constraints, runner/benchmark dependencies, options, modules,
file artifact hashes, Lua-engine library closure, server library closure,
`pandoc-cli` executable semantics, conditional branches, Cabal plan stability,
benchmark fixture payload semantics, benchmark entry-source semantics,
benchmark UTF-8/error semantics, package/project source repository closure, or
the prior descriptor-only Cabal dry-run command targets and workspace
descriptor.

The owned behavior is only the additional `cabal.project` package stanza field
closure gate before any Cabal invocation.

## Follow-Up

Hydrate or verify the pinned Pandoc checkout and run the same native static
audit against real `pandoc.cabal`, `pandoc-cli`, `pandoc-lua-engine`,
`pandoc-server`, `cabal.project`, and `cabal.project.freeze` sources before any
explicitly authorized non-mutating Cabal plan. Keep Pandoc/Cabal/Haskell runner
and benchmark execution parked unless explicitly authorized.
