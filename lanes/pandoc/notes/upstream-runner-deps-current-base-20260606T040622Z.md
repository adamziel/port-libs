# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T040622Z`.

Accepted base: `aacd91f0c62d29521f76ed00e1ea16c126d3b35d`.

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. No Pandoc binary, Cabal solver/build/test command,
Haskell test binary, Stack command, Word, LibreOffice, `zip`/`unzip`, `tar`,
`lz4`, external template engine, TeX/PDF engine, MathJax, KaTeX, Typst,
browser renderer, roff renderer, media player, online conversion service,
online sanitizer, live provider test, or other external converter was executed
as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `aacd91f0c62d29521f76ed00e1ea16c126d3b35d`.
- The focused upstream-runner audit baseline before edits passed:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported `1 test files, 399 assertions, 0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now derives required Haskell source artifacts
from the pinned Cabal `other-modules` closure for both runner targets before a
non-mutating Cabal plan can be marked ready:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`

The audit already checked that the Cabal `other-modules` metadata was present
and that selected entry-point source snippets called those Tasty groups. This
slice closes the remaining static gap where a hydrated-looking checkout could
keep the Cabal metadata intact while omitting the corresponding module source
files under each runner source directory.

The derived closure now requires paths such as:

- `test/Tests/Readers/Docx.hs`
- `test/Tests/Readers/Org/Inline/Citation.hs`
- `test/Tests/Writers/BBCode.hs`
- `pandoc-lua-engine/test/Tests/Lua/Reader.hs`

The new focused test removes selected main-runner module sources while leaving
the Cabal metadata intact. Red-first, the audit incorrectly reported the tree
ready. After implementation, the tree is blocked with
`missing upstream runner source/golden fixture artifacts`.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded native
audit case. Full upstream runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell/Cabal build closure, not by a missing local
document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, source/golden fixture artifacts,
derived other-module Haskell source artifacts, exact source-repository pins,
package flags, solver constraints, direct runner `build-depends` constraints,
runner `other-modules`, runner default-language, runner executable options,
selected `pandoc-lua-engine` library HsLua module `build-depends`, `ghc`, and
`cabal`. Keep any Cabal solver/build plan and Haskell runner execution as a
separate explicitly authorized slice.

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
  - `1 test files, 399 assertions, 0 failures`
- Red-first focused test after adding the missing-artifact assertion:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 400 assertions, 1 failures`
  - Failure: a fixture missing main-runner module source artifacts was still
    marked ready.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 409 assertions, 0 failures`
  - PASS cases: `26`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+10` assertions
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against that checkout before any Cabal solver/build command. If the static
audit is ready, record a non-mutating Cabal plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`; keep Haskell runner execution out of this
dependency audit slice.
