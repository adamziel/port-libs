# Pandoc Upstream Runner Dependency Audit 2026-06-08

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T154925Z`
Accepted base: `601b887f334686b48983fd4538b6363d93bb9b45`

No lane rework note was present for this slice. This patch stays under
`lanes/pandoc/**` and does not execute Pandoc, Cabal, Stack, GHC test
binaries, benchmark binaries, Word, LibreOffice, zip/unzip, external template
engines, TeX/PDF engines, online services, live provider tests, or
live-service provider tests.

## Behavior

The static upstream runner dependency audit now treats Cabal alternate compiler
option fields as native/system dependency closure for runner and benchmark
stanzas:

- `ghc-prof-options`
- `ghc-shared-options`
- `ghcjs-options`

These fields can arrive through imported common stanzas, not only directly on a
test or benchmark stanza. They now flow through the same bounded audit surface
as other native/system fields, preserve double-dash option values, appear in
`nativeSystemFields` and `unexpectedNativeSystemFields`, block
`readyForNonMutatingCabalPlan`, and require the existing "no unexpected runner
or benchmark native/system dependency fields" activation gate before a
non-mutating Cabal plan can be considered ready.

## Evidence

Baseline before this slice:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 1700 assertions, 0 failures`.

Red-first with only the new fixture/test:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 1701 assertions, 1 failures`.

The failure showed `readyForNonMutatingCabalPlan` stayed `true` when
`ghc-prof-options`, `ghc-shared-options`, and `ghcjs-options` were injected
into runner and benchmark closure fixtures.

Final focused check after the source change:

`php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`

Result: `1 test files, 1740 assertions, 0 failures`.

Focused delta: `+1` TestRunner PASS case and `+40` assertions.

## Dependency Closure

No new native PHP support component is needed. The slice reuses the existing
bounded Cabal parser and `UpstreamRunnerDependencyAudit`; it only closes an
untracked static Cabal field class before any future non-mutating Cabal plan is
activated.

## Non-Overlap

This is additive to previous upstream-runner dependency audit slices covering
runner executable `ghc-options`, Cabal native/preprocessor fields, common
imports, extra source/doc/tmp/data files, Lua library dependency closure, and
benchmark/source artifact semantics. It does not add conversion behavior or
repeat a document-format support-library implementation.

## Follow-Up

Continue with another non-overlapping static Cabal closure edge, or only
hydrate the pinned upstream checkout for a reviewed non-mutating plan audit.
Do not execute upstream Pandoc runners or external conversion tools from this
lane.
