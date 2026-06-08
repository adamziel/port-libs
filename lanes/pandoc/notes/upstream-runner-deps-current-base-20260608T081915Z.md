# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T081915Z`

Accepted base: `2babfc81005409c15258ad4bc3dc30214ae452bb`

This slice stays inside the static upstream-runner dependency audit. It does
not run Pandoc, Cabal solver/build/test commands, Haskell runners, Stack,
benchmark executables, Word, LibreOffice, zip/unzip, external converters,
online services, live provider tests, or live-service provider tests.

## Change

The audit now treats unexpected Cabal `ghc-options` as runner and benchmark
closure drift. The existing parser already resolves inherited `common` stanzas
for `ghc-options`; this slice records extra options as
`unexpectedExecutableOptions`, blocks `readyForNonMutatingCabalPlan`, and adds
the drift to the activation gate and non-mutating plan requirements.

This closes a static planning gap where test or benchmark stanzas could add
extra executable/build options such as eventlog or assertion toggles while
still appearing ready for a non-mutating Cabal plan.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1323 assertions, 0 failures`.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1358 assertions, 0 failures`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the lane-local
static Cabal stanza parser and `UpstreamRunnerDependencyAudit`.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal plan. Runner execution, solver/build/test
commands, benchmark executables, and external converters remain out of scope
for this lane.

## Non-Overlap

This is additive to previous upstream-runner dependency-audit slices for
test-suite type closure, direct dependencies, missing executable options,
source directories, mixins, build tools, test/benchmark options, default and
other extensions, `cpp-options`, autogen modules, reexported modules, other
modules, extra source/doc/tmp/data files, native/system fields, conditional
branches, and source/artifact semantics. It does not change native conversion
behavior or any non-Pandoc lane.

## Follow-Up

Continue with a non-overlapping static Cabal closure gap or hydrate the pinned
Pandoc checkout only for a reviewed non-mutating runner plan. Do not execute
Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark
executables, external converters, online services, live provider tests, or
live-service provider tests from this lane.
