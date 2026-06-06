# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T043639Z`.

Accepted base: `535d2618af976dc050613fd484f4b283244dcab4`.

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
  `535d2618af976dc050613fd484f4b283244dcab4`.
- The focused upstream-runner audit baseline before edits passed:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported `1 test files, 409 assertions, 0 failures`.
- Static source truth was inspected from the pinned upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: `cabal.project`,
  `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, and
  `benchmark/benchmark-pandoc.hs`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records a bounded static closure for the
pinned `benchmark benchmark-pandoc` Cabal component before a non-mutating
Cabal plan can be marked ready.

The audit already covered the two Tasty test runners:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`

This slice adds benchmark closure for:

- `benchmark:benchmark-pandoc`

The benchmark closure now tracks:

- package file: `pandoc.cabal`
- component type: `exitcode-stdio-1.0`
- `buildable` state
- `main-is`: `benchmark-pandoc.hs`
- source directory: `benchmark`
- effective `Haskell2010` default language inherited through
  `common-executable`
- direct build dependencies: `base`, `pandoc`, `bytestring`, `deepseq`,
  `mtl`, `tasty-bench`, and `text`
- selected pinned dependency constraints for `base`, `mtl`, `tasty-bench`,
  and `text`
- effective RTS options inherited through `common-executable`
- source/data artifacts: `benchmark/benchmark-pandoc.hs`,
  `test/testsuite.txt`, `test/lalune.jpg`, and `test/movie.jpg`

The focused test keeps both Tasty runner closures valid while drifting the
benchmark component and benchmark artifacts. The audit now blocks that tree
with benchmark-specific dependency, entry-point, default-language, and
artifact diagnostics instead of treating the checkout as ready for a plan.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, source/golden fixture artifacts,
benchmark source/data artifacts, derived other-module Haskell source artifacts,
exact source-repository pins, package flags, solver constraints, direct runner
and benchmark `build-depends` constraints, runner `other-modules`, default
languages, executable options, selected `pandoc-lua-engine` library HsLua
module `build-depends`, `ghc`, and `cabal`. Keep any Cabal solver/build plan,
Haskell runner execution, and benchmark execution as separate explicitly
authorized slices.

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
  - `1 test files, 409 assertions, 0 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 436 assertions, 0 failures`
  - PASS cases: `27`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+27` assertions
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against that checkout before any Cabal solver/build command. If the static
audit is ready, record a non-mutating Cabal plan for `test:test-pandoc`,
`test:test-pandoc-lua-engine`, and `benchmark:benchmark-pandoc`; keep Haskell
runner and benchmark execution out of this dependency audit slice.
