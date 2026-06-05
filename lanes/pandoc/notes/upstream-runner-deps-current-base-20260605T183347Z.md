# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T183347Z`.

Accepted base: `491fa94b2ad9759bb28ac262b0ad00542377c4c9`.

This is an upstream-runner dependency audit slice. No Pandoc binary, Cabal
solver/build/test command, Haskell test binary, `stack`, Word, LibreOffice,
`zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
MathJax, KaTeX, Typst, browser renderer, roff renderer, media player, online
conversion service, online sanitizer, or other external converter was executed
as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `491fa94b2ad9759bb28ac262b0ad00542377c4c9`.
- A bounded filename scan under `/home/claude/port-libs/.upstream-cache` found
  no `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- `ghc --numeric-version` reported `9.10.3`.
- `cabal --numeric-version` reported `3.12.1.0`.
- `stack` and `pandoc` were not found on `PATH`.
- Baseline focused audit test before this slice passed with
  `1 test files, 214 assertions, 0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records Cabal `default-language` for the
two pinned runner test-suite stanzas:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`

Both runner stanzas must expose `Haskell2010` before the audit reports
`readyForNonMutatingCabalPlan: true`. A hydrated-looking fixture with exact
project pins, package entries, constraints, runner files, selected
source/golden artifacts, entry-source snippets, direct build-depends,
executable options, and `other-modules` now remains blocked if `test-pandoc`
drifts to `Haskell98` or if `test-pandoc-lua-engine` omits
`default-language`.

This closes a static dependency-planning gap where a checkout could be marked
ready for a non-mutating Cabal plan without preserving the Haskell language
baseline for the test executables that would later compile the pinned Tasty
runner entry sources.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required
Cabal project/package files, runner entry files, selected source/golden
fixture artifacts, exact source-repository pins, package flags, solver
constraints, `Haskell2010` default-language closure, runner direct
build-depends, runner `other-modules`, runner executable options, `ghc`, and
`cabal`. Only then record a non-mutating Cabal solver/build plan for
`test:test-pandoc` and `test:test-pandoc-lua-engine`.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and one PHP PASS case only.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 214 assertions, 0 failures`
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 235 assertions, 0 failures`
  - PASS cases: `13`
- Toolchain inventory:
  `ghc --numeric-version`
  - `9.10.3`
- Toolchain inventory:
  `cabal --numeric-version`
  - `3.12.1.0`
- Toolchain inventory:
  `command -v stack || true; command -v pandoc || true`
  - no output
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - passed with no output
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
