# Upstream Runner Dependency Audit - Benchmark Fixture Semantics

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T041826Z`
- Accepted base: `8545b79dd7a73e9ae0947d693d1f23920ee07f78`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.
- Upstream cache scan: no hydrated `pandoc` checkout was present under
  `/home/claude/port-libs/.upstream-cache` for this audit.
- Source truth: pinned `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`,
  using the lane-local static inventory for `test/testsuite.txt` section/image
  markers and benchmark fixture references.

## Behavior

`UpstreamRunnerDependencyAudit` now treats benchmark data files as semantic
preconditions before a non-mutating Cabal plan. A hydrated checkout is blocked
from `readyForNonMutatingCabalPlan` when:

- `test/testsuite.txt` is non-empty but does not expose the expected Pandoc
  testsuite sections used by `benchmark/benchmark-pandoc.hs`;
- the benchmark testsuite fixture does not include the `lalune` reference image
  and inline `movie.jpg` image markers from the upstream Images section;
- `test/lalune.jpg` or `test/movie.jpg` is non-empty but lacks JPEG SOI/EOI
  boundary markers.

This extends the accepted benchmark stanza, artifact presence/provenance, and
entry-source semantic gates. It does not run Pandoc, Cabal, Haskell runners, or
the benchmark executable.

## Evidence

Initial focused run after implementation exposed one test assertion wording
mismatch:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2460 assertions, 1 failures`

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2461 assertions, 0 failures`

Delta against the previous detailed upstream-runner audit baseline: `+1`
focused PHP PASS case and `+16` focused assertions.

Final dependency-audit family run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`

Result: `3 test files, 2558 assertions, 0 failures`

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
or benchmark entry-source semantics.

The owned behavior is only `benchmark-pandoc` fixture payload semantics for
`test/testsuite.txt`, `test/lalune.jpg`, and `test/movie.jpg` before Cabal
planning.

## Follow-Up

If upstream-runner dependency work remains active, either hydrate the pinned
Pandoc checkout with explicit authorization and run the native static audit
against real Cabal/source files before recording a non-mutating Cabal plan, or
choose another non-overlapping static gate around upstream runner package
semantics. Keep Pandoc/Cabal/Haskell runner and benchmark execution parked
unless explicitly authorized.
