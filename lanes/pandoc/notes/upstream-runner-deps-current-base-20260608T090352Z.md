# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T090352Z`

Accepted base: `b3a6a457e189843a6281cb033c461a4ee4341587`

This slice stays inside the static upstream-runner dependency audit. It does
not run Pandoc, Cabal solver/build/test commands, Haskell runners, Stack,
benchmark executables, Word, LibreOffice, zip/unzip, external converters,
online services, live provider tests, or live-service provider tests.

## Change

The audit now treats unexpected Cabal native/preprocessor fields as runner and
benchmark closure drift before any non-mutating Cabal runner plan can be
marked ready. The added fields are:

- `asm-options`
- `cmm-options`
- `js-options`
- `hsc2hs-options`
- `c2hs-options`
- `extra-lib-dirs-static`
- `extra-bundled-libraries`

The parser also preserves double-dash option tokens in these native option
fields, such as `--cross-compile` and `--cppopts=...`, while still stripping
plain Cabal line comments. This closes a static planning gap where preprocessor
or bundled/static-library inputs could be added to the runner or benchmark
stanzas without appearing in the dependency-closure blocker.

## Evidence

- Rework-note check: no `port-pandoc-*.needs-lane-rework.md` note existed for
  this slice.
- Red-first focused test: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  initially failed because `hsc2hs-options`, `c2hs-options`, and `js-options`
  double-dash values were stripped as comments before native field extraction.
- Final focused test: `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  passed with `1 test files, 1398 assertions, 0 failures`.
- PHP lint passed for changed PHP files.
- Lane JSON validation passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness not run - isolated micro-slice.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the lane-local
static Cabal stanza parser and `UpstreamRunnerDependencyAudit`.

Full upstream runner parity remains gated on a hydrated pinned Pandoc checkout
and a reviewed non-mutating Cabal plan. Runner execution, solver/build/test
commands, benchmark executables, zip/unzip, external converters, and online
services remain out of scope for this lane.

## Non-Overlap

This is additive to previous upstream-runner dependency-audit slices for
test-suite type closure, direct dependencies, missing and unexpected executable
options, source directories, mixins, build tools, test/benchmark options,
default and other extensions, `cpp-options`, autogen modules, reexported
modules, other modules, extra source/doc/tmp/data files, native/system fields,
native header includes, conditional branches, and source/artifact semantics.
It does not change native conversion behavior or any non-Pandoc lane.

## Follow-Up

Continue with a non-overlapping static Cabal closure gap or hydrate the pinned
Pandoc checkout only for a reviewed non-mutating runner plan. Do not execute
Pandoc, Cabal solver/build/test commands, Haskell runners, Stack, benchmark
executables, external converters, online services, live provider tests, or
live-service provider tests from this lane.
