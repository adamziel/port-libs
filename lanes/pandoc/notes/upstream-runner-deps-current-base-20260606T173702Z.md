# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T173702Z`.

Accepted base: `9e3c99a8e5c01950dfc5cf7a611b50350af53219`.

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
  `9e3c99a8e5c01950dfc5cf7a611b50350af53219`.
- A bounded filename scan under `/home/claude/port-libs/.upstream-cache` found
  no local `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- Pinned upstream source truth at commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` records these package headers:
  `pandoc.cabal` is `name: pandoc`, `version: 3.9.0.2`,
  `cabal-version: 2.4`, `build-type: Simple`; and
  `pandoc-lua-engine/pandoc-lua-engine.cabal` is
  `name: pandoc-lua-engine`, `version: 0.5.2`,
  `cabal-version: 2.4`, `build-type: Simple`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records effective Cabal package identity
headers for:

- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`

The static audit now blocks a non-mutating Cabal plan when either package file
is missing its expected `name`, `version`, `cabal-version`, or `build-type`
header, or when those headers drift from the pinned upstream values. This
prevents a hydrated checkout from being marked runner-ready against stale or
misidentified Cabal package files before `test:test-pandoc`,
`test:test-pandoc-lua-engine`, or `benchmark:benchmark-pandoc` dependency
closure is trusted.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and explicitly authorized Haskell/Cabal build
closure, not by a missing local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required
Cabal project/package files, package identity/version headers, runner entry
files, exact source-repository pins, package flags, solver constraints, the
`pandoc.cabal` tested-with GHC matrix, runner direct `build-depends`
constraints, runner `other-modules`, runner default-language, runner
executable options, selected `pandoc-lua-engine` library HsLua module
`build-depends`, non-empty runner source/golden fixture artifacts, non-empty
benchmark source/data artifacts, no unexpected runner or benchmark mixins, no
runner or benchmark build-tool dependencies, no unexpected runner or benchmark
default-extensions, no unexpected runner or benchmark other-extensions, no
unexpected runner or benchmark `cpp-options`, no unexpected runner or
benchmark `autogen-modules`, supported `ghc`, and `cabal`. Keep any Cabal
solver/build plan, Haskell runner execution, and benchmark execution as
separate explicitly authorized slices.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - No syntax errors detected.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 728 assertions, 0 failures`
  - PASS cases: `38`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+18` assertions.

No example smoke was added or run; this slice is an upstream-runner dependency
audit with no user-visible WordPress conversion path.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against real `cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, package identity/version headers,
runner entry files, runner artifacts, benchmark artifacts, the tested-with GHC
matrix, runner and benchmark dependency constraints, default-extension
closure, other-extension closure, `cpp-options` closure, and
`autogen-modules` closure before any Cabal solver/build command. If the static
audit is ready, record a non-mutating Cabal plan for `test:test-pandoc`,
`test:test-pandoc-lua-engine`, and `benchmark:benchmark-pandoc`; keep Haskell
runner and benchmark execution out of this dependency audit slice.
