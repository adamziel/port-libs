# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T131535Z`.

Accepted base: `44faa0785161b3473e67f1672535e2c667b87e36`.

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
  `44faa0785161b3473e67f1672535e2c667b87e36`.
- A bounded filename scan under `/home/claude/port-libs/.upstream-cache` found
  no `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- `command -v ghc` and `command -v cabal` found `/usr/bin/ghc` and
  `/usr/bin/cabal`; `command -v stack` returned no path.
- `ghc --numeric-version` returned `9.10.3`; `cabal --numeric-version`
  returned `3.12.1.0`.
- Static source truth was checked against the pinned upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: the runner and benchmark Cabal
  stanzas do not carry `other-extensions` fields, so adding them in a hydrated
  checkout is dependency-closure drift rather than required runner state.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records Cabal `other-extensions` for:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`
- `benchmark:benchmark-pandoc`

The expected static closure remains empty for those components. A checkout that
keeps package files, project pins, entry points, direct dependencies, source
artifacts, runner entry semantics, mixins, build tools, and default extensions
can no longer be marked ready for a non-mutating Cabal plan if the runner or
benchmark stanzas add unexpected `other-extensions` such as `CPP`,
`OverloadedStrings`, `FlexibleContexts`, `TypeApplications`, or `DeriveGeneric`.

The parser also merges `other-extensions` through imported Cabal common stanzas,
matching the existing static handling for build dependencies, mixins, build
tools, default extensions, and other modules.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded native
audit case. Full upstream runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell/Cabal build closure, not by a missing local
document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, exact source-repository pins,
package flags, solver constraints, the `pandoc.cabal` tested-with GHC matrix,
runner direct `build-depends` constraints, runner `other-modules`, runner
default-language, runner executable options, selected `pandoc-lua-engine`
library HsLua module `build-depends`, non-empty runner source/golden fixture
artifacts, non-empty benchmark source/data artifacts, no unexpected runner or
benchmark mixins, no runner or benchmark build-tool dependencies, no unexpected
runner or benchmark default-extensions, no unexpected runner or benchmark
other-extensions, supported `ghc`, and `cabal`. Keep any Cabal solver/build
plan, Haskell runner execution, and benchmark execution as separate explicitly
authorized slices.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Red-first focused test after adding the `other-extensions` drift fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 605 assertions, 1 failures`
  - Failure: the audit still marked a checkout ready after runner and
    benchmark stanzas added unexpected `other-extensions`.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 638 assertions, 0 failures`
  - PASS cases: `35`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+34` assertions

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against real `cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry files, runner
artifacts, benchmark artifacts, the tested-with GHC matrix, runner and
benchmark dependency constraints, default-extension closure, and
other-extension closure before any Cabal solver/build command. If the static
audit is ready, record a non-mutating Cabal plan for `test:test-pandoc`,
`test:test-pandoc-lua-engine`, and `benchmark:benchmark-pandoc`; keep Haskell
runner and benchmark execution out of this dependency audit slice.
