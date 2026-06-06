# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T103303Z`.

Accepted base: `aafdefee09bf90e527df1bcd5b451a92fb989b76`.

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
  `aafdefee09bf90e527df1bcd5b451a92fb989b76`.
- A bounded filename scan under `/home/claude/port-libs/.upstream-cache` found
  no `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- `command -v ghc` and `command -v cabal` found `/usr/bin/ghc` and
  `/usr/bin/cabal`; `command -v stack` returned no path.
- `ghc --numeric-version` returned `9.10.3`; `cabal --numeric-version`
  returned `3.12.1.0`.
- The pinned upstream `pandoc.cabal` at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` records tested compiler
  versions `GHC == 9.6.7`, `9.8.4`, `9.10.3`, and `9.12.2`.
- The focused upstream-runner audit baseline before edits passed:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported `1 test files, 532 assertions, 0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now parses the top-level `tested-with` field in
`pandoc.cabal`, records the present GHC version matrix, normalizes the local
`ghc` tool version string, and blocks `readyForNonMutatingCabalPlan` when:

- the checkout omits one of the expected Pandoc tested-with GHC versions
  `9.6.7`, `9.8.4`, `9.10.3`, or `9.12.2`; or
- the available `ghc` tool version cannot be normalized to one of those
  expected versions.

The parser handles comma-separated and continuation-line `tested-with` entries
and strips Cabal line comments before extracting `GHC == ...` versions.

This closes a static dependency-planning gap: a hydrated-looking checkout could
previously be marked ready for a non-mutating Cabal plan even when its
`pandoc.cabal` compiler support matrix was stale or the local compiler was an
unsupported version such as `9.14.1`.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, exact source-repository pins,
package flags, solver constraints, the `pandoc.cabal` tested-with GHC matrix,
runner direct `build-depends` constraints, runner `other-modules`, runner
default-language, runner executable options, selected `pandoc-lua-engine`
library HsLua module `build-depends`, non-empty runner source/golden fixture
artifacts, non-empty benchmark source/data artifacts, no unexpected runner or
benchmark mixins, no runner or benchmark build-tool dependencies, supported
`ghc`, and `cabal`. Keep any Cabal solver/build plan, Haskell runner
execution, and benchmark execution as separate explicitly authorized slices.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 532 assertions, 0 failures`
- Red-first focused test after adding the stale tested-with/unsupported-ghc
  fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 483 assertions, 2 failures`
  - Failure: a fixture with missing tested-with GHC `9.10.3` and local
    `ghc` version `9.14.1` was still marked ready.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 548 assertions, 0 failures`
  - PASS cases: `32`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+16` assertions
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against real `cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry files, runner
artifacts, benchmark artifacts, and the tested-with GHC matrix before any
Cabal solver/build command. If the static audit is ready, record a
non-mutating Cabal plan for `test:test-pandoc`, `test:test-pandoc-lua-engine`,
and `benchmark:benchmark-pandoc`; keep Haskell runner and benchmark execution
out of this dependency audit slice.
