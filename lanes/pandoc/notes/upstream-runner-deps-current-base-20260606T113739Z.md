# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T113739Z`.

Accepted base: `a238e36374993d9d8ee1e4b4fb86ace86acaec5e`.

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
  `a238e36374993d9d8ee1e4b4fb86ace86acaec5e`.
- A bounded filename scan under `/home/claude/port-libs/.upstream-cache` found
  no `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- `command -v ghc` and `command -v cabal` found `/usr/bin/ghc` and
  `/usr/bin/cabal`; `command -v stack` returned no path.
- `ghc --numeric-version` returned `9.10.3`; `cabal --numeric-version`
  returned `3.12.1.0`.
- Static source truth was inspected from pinned upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: `test/test-pandoc.hs`,
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`, `pandoc.cabal`, and
  `pandoc-lua-engine/pandoc-lua-engine.cabal`.
- The accepted focused upstream-runner audit baseline before this slice was
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  at `1 test files, 548 assertions, 0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records a stricter pinned
`test/test-pandoc.hs` entry-source closure before a non-mutating Cabal plan can
be marked ready.

The previous audit checked that the main test runner exposed a generic
`--emulate` path and selected Tasty groups. This slice closes the remaining
static gap where a hydrated-looking checkout could keep the Cabal metadata,
entry file, `convertWithOpts noEngine` call, and source/golden artifacts while
silently replacing the upstream command emulation and omitting whole reader or
writer groups from `defaultMain`.

The audit now requires:

- `E.catch` command-emulation exception handling.
- `parseOptionsFromArgs options defaultOpts "pandoc" args'` argument
  forwarding for the `--emulate` path.
- `handleOptInfo noEngine`, `convertWithOpts noEngine opts`, and
  `handleError . Left` error/option handling.
- The remaining pinned main-runner reader groups: JATS, Jira, Org, RTF,
  Txt2Tags, Muse, Creole, Man, Mdoc, and DokuWiki.
- The remaining pinned main-runner writer groups: ConTeXt, JATS, Jira, Org,
  Plain, Markua, Muse, FB2, and Ms.

The focused test fixture now mirrors the pinned upstream main runner shape for
this static audit. The new negative fixture strips the command-emulation parser
and extended reader/writer Tasty groups while preserving Cabal closure and
source artifacts; the audit blocks it with
`missing runner entry point source semantics`.

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
`ghc`, `cabal`, and the stricter `test/test-pandoc.hs` command-emulation plus
full Tasty group entry-source semantics. Keep any Cabal solver/build plan,
Haskell runner execution, and benchmark execution as separate explicitly
authorized slices.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Red-first focused test after adding the stricter entry-source fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 558 assertions, 1 failures`
  - Failure: the audit only reported the older generic
    `uses noEngine for command emulation` snippet and missed the stricter
    command-emulation parser/error-handling plus extended Tasty group
    semantics.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 571 assertions, 0 failures`
  - PASS cases: `33`
  - Focused delta from accepted upstream-runner audit baseline:
    `+1` PASS case / `+23` assertions
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - Passed with no output.
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
