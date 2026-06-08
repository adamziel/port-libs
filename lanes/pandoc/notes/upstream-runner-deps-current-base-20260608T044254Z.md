# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T044254Z`.

Accepted base: `6aca64b1e7abf114b75d86b491d7c036d94a8253`.

This is an upstream-runner dependency audit slice, not a Haskell runner or
converter execution slice. No Pandoc binary, Cabal solver/build/test command,
Haskell test binary, Stack command, benchmark executable, Word, LibreOffice,
`zip`/`unzip`, external converter, online service, live provider test, or
live-service provider test was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `6aca64b1e7abf114b75d86b491d7c036d94a8253`.
- `/home/claude/port-libs/.upstream-cache/pandoc` is absent in this worker
  environment, so this slice used the lane-local pinned audit fixture and
  current accepted `UpstreamRunnerDependencyAudit` contract as source truth.
- Baseline focused audit coverage before this slice was
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  with `1 test files, 1183 assertions, 0 failures`.

## Change

`UpstreamRunnerDependencyAudit` now treats Cabal `extra-doc-files` as part of
the static runner dependency closure before non-mutating Cabal planning:

- `test:test-pandoc` and `test:test-pandoc-lua-engine` expose expected,
  present, and unexpected runner `extraDocFiles` closure arrays.
- `benchmark:benchmark-pandoc` exposes expected, present, and unexpected
  benchmark `extraDocFiles` closure arrays.
- Imported common Cabal stanzas merge `extra-doc-files` the same way existing
  comma-style closure fields such as `extra-source-files` and `data-files`
  merge.
- Any unexpected runner or benchmark `extra-doc-files` now blocks readiness
  with explicit diagnostics before a Cabal plan can be marked ready.
- The non-mutating activation plan now records `extra-doc-files` alongside
  source and data file closure.

The focused PHP test adds a drift fixture where common runner stanzas, the Lua
runner, and the benchmark add doc-file globs. The audit must keep the plan
unready and surface those globs without mutating or executing upstream tools.

## Dependency Closure

No new native PHP support component is needed. This slice reuses the existing
lane-local static Cabal stanza parser and closure audit. It does not activate
Pandoc, Cabal, Haskell runners, Stack, benchmark executables, external office
tools, archive tools, template engines, online services, or live provider
tests.

The upstream runner gate remains bounded: hydrate the pinned Pandoc checkout
only for a future reviewed non-mutating plan, then record exact Cabal package,
source-repository, flag, runner, benchmark, and fixture closure before any
solver/build/test command is considered.

## Non-Overlap

This slice does not touch native format conversion behavior. It avoids current
DOCX/OpenXML, EPUB3, ODT/OpenDocument, archive compression, charset/Unicode,
syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX, table-geometry, math/TeX, PDF
handoff, XML/HTML5 DOM, and legacy DOC/CFB support-library surfaces.

It is additive to the accepted upstream-runner dependency audit slices for
test-suite type, other-extensions, autogen-modules, test/benchmark options,
and data-files. This slice owns only the `extra-doc-files` static closure gap.

## Verification

- Baseline focused audit run before the slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 1183 assertions, 0 failures`
- Source-only red check before expectation updates:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 1180 assertions, 1 failures`
  - failure was the existing non-mutating plan text expecting no
    `extra-doc-files` clause yet.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 1236 assertions, 0 failures`
- PHP syntax checks:
  - `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`: no syntax
    errors.
  - `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`: no
    syntax errors.
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next

For upstream-runner dependency follow-up, choose a non-overlapping static Cabal
closure gap, or hydrate the pinned Pandoc checkout for a reviewed non-mutating
plan only. Do not execute Pandoc, Cabal solver/build/test commands, Haskell
runners, Stack, benchmark executables, external converters, online services,
live provider tests, or live-service provider tests from this lane.
