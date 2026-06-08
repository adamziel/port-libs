# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T074250Z`

Accepted base: `b8ff3cf9b25772df3390d1b24204be6f5e889d6b`

This slice stays inside the static upstream-runner dependency audit. It does
not run Pandoc, Cabal solver/build/test commands, Haskell runners, Stack,
benchmark executables, Word, LibreOffice, zip/unzip, external converters,
online services, live provider tests, or live-service provider tests.

## Change

The audit now treats Cabal `extra-tmp-files` as runner and benchmark closure
data. Inherited/common and direct `extra-tmp-files` entries are parsed for
`test:test-pandoc`, `test:test-pandoc-lua-engine`, and
`benchmark:benchmark-pandoc`, surfaced in the closure packets, reported as
unexpected drift, and included in the activation gate and non-mutating plan
requirements.

This closes a static planning gap where extra temporary runner or benchmark
artifacts could be added without blocking `readyForNonMutatingCabalPlan`.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this slice.
- Baseline: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1266 assertions, 0 failures`.
- Red-first: the same focused test failed as expected with
  `1 test files, 1267 assertions, 1 failures` because unexpected
  `extra-tmp-files` still left `readyForNonMutatingCabalPlan` true.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1323 assertions, 0 failures`.
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

This is additive to the previous upstream-runner dependency-audit slices for
test-suite type closure, direct dependencies, default extensions, other
extensions, autogen modules, test/benchmark options, extra source/doc/data
files, native/system fields, conditional branches, source directories, mixins,
build tools, and other modules. It does not change native conversion behavior
or any non-Pandoc lane.

## Follow-Up

Continue with a non-overlapping static Cabal closure gap or hydrate the pinned
Pandoc checkout only for a reviewed non-mutating runner plan. Do not execute
Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark
executables, external converters, online services, live provider tests, or
live-service provider tests from this lane.
