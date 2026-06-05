# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T180329Z`.

Accepted base: `2c19a701a31b0f790d90d0420fa2b95cd56a6265`.

This is an upstream-runner dependency audit slice. No Pandoc binary, Cabal
solver/build/test command, Haskell test binary, `stack`, Word, LibreOffice,
`zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
MathJax, KaTeX, Typst, browser renderer, roff renderer, media player, online
conversion service, online sanitizer, or other external converter was executed
as progress.

## Current-Base Evidence

- Only stale May `port-pandoc-*.needs-lane-rework.md` notes were present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates/stale`; no current
  Pandoc rework note was present for this base/session.
- This isolated worktree started clean at accepted base
  `2c19a701a31b0f790d90d0420fa2b95cd56a6265`.
- `/home/claude/port-libs/.upstream-cache/pandoc` is not present, and a
  bounded filename scan under `/home/claude/port-libs/.upstream-cache` found no
  `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- Source truth for the added closure field is the pinned upstream Pandoc
  `pandoc.cabal` and
  `pandoc-lua-engine/pandoc-lua-engine.cabal` metadata at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, plus the already accepted
  lane-local runner entry-source semantics for the selected Tasty modules.
- Baseline focused audit test before this slice passed with
  `1 test files, 194 assertions, 0 failures`.
- The red-first focused test failed with `1 test files, 22 assertions,
  10 failures` after the fixture requested expected runner module closure but
  `UpstreamRunnerDependencyAudit::expectedRunnerOtherModules()` did not exist.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records and validates selected Cabal
`test-suite` `other-modules` closure for both upstream runner targets:

- `test:test-pandoc` requires `Tests.Command`, `Tests.Readers.Markdown`,
  `Tests.Writers.Markdown`, and `Tests.Writers.Native`.
- `test:test-pandoc-lua-engine` requires `Tests.Lua`, `Tests.Lua.Module`,
  `Tests.Lua.Reader`, and `Tests.Lua.Writer`.

The audit parses `other-modules`, including inherited/repeated Cabal fields,
exposes `expectedOtherModules`, records present `otherModules`, and blocks
`readyForNonMutatingCabalPlan` with a concrete `missing Cabal runner
other-modules` reason if those selected modules drift out of package metadata.

This closes a dependency-planning gap where Cabal project/package closure,
source-repository pins, runner fixture roots, file hashes, executable options,
direct build-depends, and entry-source snippets could all be present while the
package metadata no longer declared the selected runner modules Cabal needs to
build the intended Tasty test executable.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required
Cabal project/package files, runner entry files, selected source/golden fixture
artifacts, exact source-repository pins, package flags, solver constraints,
runner direct build-depends, runner `other-modules`, runner executable options,
`ghc`, and `cabal`. Only then record a non-mutating Cabal solver/build plan for
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
  - `1 test files, 194 assertions, 0 failures`
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - failed before implementation with `1 test files, 22 assertions,
    10 failures` because expected runner other-module closure was not exposed.
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 214 assertions, 0 failures`
  - PASS cases: `12`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc`
  - passed
- Root harness: not run - isolated micro-slice.
