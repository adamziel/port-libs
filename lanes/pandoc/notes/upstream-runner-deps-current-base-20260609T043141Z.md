# Upstream Runner Dependency Audit - Benchmark Format Registry Semantics

- Micro-slice: `pandoc-upstream-runner-deps-current-base-20260609T043141Z`
- Accepted base: `75e61bcf0bd749a29b9d57093a23d6f3b6828b00`
- Rework notes: no current `port-pandoc-*.needs-lane-rework.md` note was present.
- Upstream cache scan: no hydrated `pandoc` checkout was present under `/home/claude/port-libs/.upstream-cache` for this audit.
- Source truth: pinned `jgm/pandoc@0640c4c9859aa5a3ede082c190fcd5883c24ac83`, using the lane-local static inventory for `benchmark/benchmark-pandoc.hs`.

## Behavior

`UpstreamRunnerDependencyAudit` now treats benchmark format-registry dispatch as
a semantic precondition before non-mutating Cabal-plan readiness. A hydrated
checkout is blocked when `benchmark/benchmark-pandoc.hs` no longer:

- wraps writer benchmarks in `env getImages`, preserving media fixture
  insertion for writer timing;
- maps `writers :: [(T.Text, Writer PandocPure)]` through `writerBench`;
- maps `readers :: [(T.Text, Reader PandocPure)]` through `readerBench`.

This extends the accepted benchmark component dependency, artifact, fixture,
and entry-source gates. It does not run Pandoc, Cabal, Haskell runners, or the
benchmark executable.

## Evidence

Baseline focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2461 assertions, 0 failures`.

Final focused run:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 2473 assertions, 0 failures`.

Delta: `+1` focused PHP PASS case and `+12` focused assertions.

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
benchmark fixture payload semantics, or generic benchmark entry-source
semantics.

The owned behavior is only `benchmark-pandoc` format-registry benchmark source
semantics for media-wrapped writer benchmarks and writer/reader registry group
dispatch before Cabal planning.

## Follow-Up

If upstream-runner dependency work remains active, either hydrate the pinned
Pandoc checkout with explicit authorization and run the native static audit
against real Cabal/source files before recording a non-mutating Cabal plan, or
choose another non-overlapping static gate around upstream runner package
semantics. Keep Pandoc/Cabal/Haskell runner and benchmark execution parked
unless explicitly authorized.
