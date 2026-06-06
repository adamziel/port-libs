# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T064553Z`.

Accepted base: `efaf7892c3f0240c764f0fe029726e5aaf7397ce`.

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. No Pandoc binary, Cabal solver/build/test command,
Haskell test binary, benchmark executable, Stack command, Word, LibreOffice,
`zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
MathJax, KaTeX, Typst, browser renderer, roff renderer, media player, online
conversion service, online sanitizer, live provider test, or other external
converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `efaf7892c3f0240c764f0fe029726e5aaf7397ce`.
- The focused upstream-runner audit baseline before edits passed:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported `1 test files, 460 assertions, 0 failures`.
- Local static cache searches found no usable hydrated Pandoc checkout,
  `pandoc.cabal`, `pandoc-lua-engine.cabal`, or `cabal.project` under
  `/home/claude/port-libs/.upstream-cache`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now parses Cabal `mixins` fields for the
upstream-runner dependency closure before a non-mutating Cabal plan can be
marked ready.

The existing audit already checked required package files, source-repository
pins, package flags, solver constraints, direct dependencies, dependency
version constraints, executable options, default languages, other-modules,
source/golden artifacts, runner entry-source semantics, and benchmark
entry-source semantics.

This slice closes the static gap where a hydrated-looking checkout could keep
that metadata intact while adding Cabal mixins that hide or rename modules for:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`
- `benchmark:benchmark-pandoc`

Unexpected runner or benchmark mixins now block readiness with explicit
`unexpected Cabal runner mixins` or `unexpected Cabal benchmark mixins`
diagnostics. The parser preserves comma-separated mixins that contain nested
parenthesized module lists, such as `base hiding (Prelude, Data.List)`, so
audit output remains stable enough for review.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, source/golden fixture artifacts,
benchmark source/data artifacts, derived other-module Haskell source
artifacts, exact source-repository pins, package flags, solver constraints,
direct runner and benchmark `build-depends` constraints, runner
`other-modules`, default languages, executable options, no unexpected runner
or benchmark mixins, selected `pandoc-lua-engine` library HsLua module
`build-depends`, `ghc`, and `cabal`. Keep any Cabal solver/build plan, Haskell
runner execution, and benchmark execution as separate explicitly authorized
slices.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive compression,
charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX, table
geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 460 assertions, 0 failures`
- Red-first focused test after adding the mixin-drift fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Failed with `1 test files, 461 assertions, 1 failures` because a fixture
    with runner and benchmark mixins was still marked ready.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 484 assertions, 0 failures`
  - PASS cases: `29`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+24` assertions
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Passed with no output.
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against that checkout before any Cabal solver/build command. If the static
audit is ready, record a non-mutating Cabal plan for `test:test-pandoc`,
`test:test-pandoc-lua-engine`, and `benchmark:benchmark-pandoc`; keep Haskell
runner and benchmark execution out of this dependency audit slice.
