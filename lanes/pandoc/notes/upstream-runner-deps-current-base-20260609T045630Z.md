# Upstream Runner Dependency Audit - Benchmark Decode and Error Semantics

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T045630Z`
- Accepted base: `b44fa1e4c39d90d096b8a3ca7585d5a157201f99`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.
- Upstream cache scan: no hydrated `pandoc` checkout or Pandoc Cabal package/project files were present under `/home/claude/port-libs/.upstream-cache` for this audit.
- Source truth: pinned `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`, using the lane-local static inventory and benchmark source fixture for `benchmark/benchmark-pandoc.hs`.

## Behavior

`UpstreamRunnerDependencyAudit` now treats benchmark decode and error semantics
as static preconditions before non-mutating Cabal-plan readiness. A hydrated
checkout is blocked when `benchmark/benchmark-pandoc.hs` no longer:

- decodes `test/testsuite.txt` through `UTF8.toText` while reading the fixture;
- displays reader benchmark failures through `error . show` for Pandoc errors;
- reports text/bytestring reader-writer mismatches with the upstream
  `PandocSomeError` diagnostic.

This extends the accepted benchmark component dependency, artifact, fixture,
and format-registry source gates. It does not run Pandoc, Cabal, Haskell
runners, Stack, or the benchmark executable.

## Evidence

Baseline focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2473 assertions, 0 failures`.

Red-first focused run after adding only the new test:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2474 assertions, 1 failures`.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2485 assertions, 0 failures`.

Final upstream-runner audit family run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`

Result: `3 test files, 2582 assertions, 0 failures`.

Additional checks:

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- `php -r 'foreach (["lanes/pandoc/lane-status.json", "lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo $f." OK".PHP_EOL; }'`
- `git diff --check -- lanes/pandoc`

No Pandoc executable, Cabal solver/build/test command, Haskell runner, Stack
runner, benchmark executable, Word, LibreOffice, zip/unzip, tar/gzip, external
converter, external validator, online service, live provider test, or
live-service provider test was executed.

Example smoke: not run. This is an upstream-runner dependency audit-only slice
with no WordPress-visible conversion path.

Root harness: not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. This slice reuses
`UpstreamRunnerDependencyAudit`, lane-local Cabal/source fixtures, the
lane-local upstream inventory, and the focused PHP TestRunner.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal dependency plan before any Haskell executable
build, benchmark executable, or runner execution is considered in a separate
explicitly authorized slice.

## Non-Overlap

This does not repeat accepted static audits for required source files, package
identity, setup hooks, package flags/files/native fields, project pins,
packages, flags, constraints, runner/benchmark dependencies, options, modules,
file artifact hashes, Lua-engine library closure, server library closure,
`pandoc-cli` executable semantics, conditional branches, Cabal plan stability,
benchmark fixture payload semantics, generic benchmark entry-source semantics,
or benchmark format-registry dispatch.

The owned behavior is only `benchmark-pandoc` UTF-8 fixture decoding, reader
failure display, and text/bytestring mismatch diagnostics before Cabal
planning.

## Follow-Up

If upstream-runner dependency work remains active, either hydrate the pinned
Pandoc checkout with explicit authorization and run this native static audit
against real Cabal/source files before recording a non-mutating Cabal plan, or
choose another non-overlapping static gate around upstream runner package,
source, fixture, or benchmark semantics. Keep Pandoc/Cabal/Haskell runner and
benchmark execution parked unless explicitly authorized.
