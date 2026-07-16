# Pandoc Upstream Runner Dependency Audit 2026-06-08

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260608T070623Z`.

Accepted base: `b0c59c5f99abc8d96918caaa8798b022dda757b4`.

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. No Pandoc binary, Cabal solver/build/test command,
Haskell test binary, benchmark executable, Stack command, Word, LibreOffice,
`zip`/`unzip`, external template engine, TeX/PDF engine, browser renderer,
online conversion service, online sanitizer, live provider test,
live-service provider test, or other external converter was executed as
progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `b0c59c5f99abc8d96918caaa8798b022dda757b4`.
- `/home/claude/port-libs/.upstream-cache/pandoc` is absent in this worker
  environment, so this slice used the lane-local pinned audit fixture and the
  current accepted `UpstreamRunnerDependencyAudit` contract as source truth.
- Baseline focused audit coverage before this slice was
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  with `1 test files, 1236 assertions, 0 failures`.

## Change

`UpstreamRunnerDependencyAudit` now treats Cabal legacy `extensions` fields as
default-extension closure before non-mutating Cabal planning:

- `test:test-pandoc` inherits `extensions` from imported common executable
  stanzas and reports those entries through `defaultExtensions`.
- `test:test-pandoc-lua-engine` reports direct legacy `extensions` entries
  through runner default-extension closure.
- `benchmark:benchmark-pandoc` merges common inherited `extensions` with
  benchmark-local `extensions` and reports the combined set through benchmark
  default-extension closure.
- Unexpected legacy `extensions` entries now block
  `readyForNonMutatingCabalPlan` with the same diagnostics and activation gate
  used for unexpected `default-extensions`.

The focused PHP test adds drift fixtures for common runner stanzas, the Lua
runner, and the benchmark. The audit must keep the plan unready and surface the
unexpected extensions without mutating or executing upstream tools.

## Dependency Closure

No new native PHP conversion support component is needed. This slice reuses the
existing lane-local static Cabal stanza parser and dependency audit. It does
not activate Pandoc, Cabal, Haskell runners, Stack, benchmark executables,
external office tools, archive tools, template engines, online services, live
provider tests, or live-service provider tests.

The upstream runner gate remains bounded: hydrate the pinned Pandoc checkout
only for a future reviewed non-mutating plan, then record exact Cabal package,
source-repository, flag, runner, benchmark, fixture, option, extension, module,
data, and native/system closure before any solver/build/test command is
considered.

## Non-Overlap

This slice does not touch native format conversion behavior. It avoids current
DOCX/OpenXML, EPUB3, ODT/OpenDocument, archive compression, charset/Unicode,
syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX, table-geometry, math/TeX, PDF
handoff, XML/HTML5 DOM, and legacy DOC/CFB support-library surfaces.

It is additive to accepted upstream-runner dependency audit slices for
test-suite type, direct dependencies, default-extensions, other-extensions,
autogen-modules, test/benchmark options, extra-source-files, extra-doc-files,
and data-files. This slice owns only the legacy `extensions` alias folded into
default-extension closure.

## Verification

- Baseline focused audit run before the slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 1236 assertions, 0 failures`
- Red-first focused run after adding the failing fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 1237 assertions, 1 failures`
  - Failure cause: the audit still treated legacy `extensions` drift as ready,
    leaving `readyForNonMutatingCabalPlan` true.
- Final focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 1266 assertions, 0 failures`
- PHP syntax checks:
  - `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`: no syntax
    errors.
  - `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`: no
    syntax errors.
- JSON validation for `lanes/pandoc/lane-status.json` and
  `lanes/pandoc/UPSTREAM_TEST_MANIFEST.json`
  - `pandoc JSON ok`
- `git diff --check -- lanes/pandoc`
  - Passed with no output.

No example smoke was added or run; this slice is an upstream-runner dependency
audit with no user-visible WordPress conversion path.

Root harness: not run - isolated micro-slice.

## Next

For upstream-runner dependency follow-up, choose a non-overlapping static Cabal
closure gap, or hydrate the pinned Pandoc checkout for a reviewed non-mutating
plan only. Do not execute Pandoc, Cabal solver/build/test commands, Haskell
runners, Stack, benchmark executables, external converters, online services,
live provider tests, or live-service provider tests from this lane.
