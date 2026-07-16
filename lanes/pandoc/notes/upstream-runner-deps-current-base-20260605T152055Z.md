# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T152055Z`.

Accepted base: `e2b7ce1ea35e45b8f524e636fbaf6b94cc04580a`.

This is an upstream-runner dependency audit slice. No Pandoc binary, Cabal
solver/build/test command, Haskell test binary, Word, LibreOffice, `zip`/
`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine, MathJax,
KaTeX, Typst, browser renderer, roff renderer, media player, online conversion
service, online sanitizer, or other external converter was executed as
progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `e2b7ce1ea35e45b8f524e636fbaf6b94cc04580a`.
- The focused upstream-runner dependency audit test passed before this slice
  with `1 test files, 113 assertions, 0 failures`.
- The lane remains on the cloned static upstream inventory at Pandoc commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`; full runner parity still
  requires a hydrated Haskell checkout and Cabal runner build plan.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records the Cabal `buildable` state for the
two pinned runner test-suite stanzas:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`

The audit treats an absent `buildable` field as Cabal's default buildable
state, accepts explicit `True`/`Yes`, and blocks explicit `False`/`No` or
invalid values before `readyForNonMutatingCabalPlan` can become true.

This closes a static dependency-planning gap: a fixture with a hydrated
`cabal.project`, exact Git source-repository pins, matching package files,
direct runner dependencies, entry points, executable options, and available
`ghc`/`cabal` could previously be marked ready even if both Haskell runner
test executables were declared `buildable: False`.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. The open blocker remains the upstream Haskell runner/build
closure, not a missing Pandoc-local format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present. Then verify
package entries, package flags, solver constraints, exact Git
source-repository package types/locations/tags, buildable `exitcode-stdio-1.0`
test-suite stanzas, entry points, direct build-depends, and runner executable
options before recording a non-mutating Cabal solver/build plan.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and one PHP PASS case only.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 113 assertions, 0 failures`
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 128 assertions, 0 failures`
  - PASS cases: `8`
- Focused Pandoc lane tests:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `21 test files, 12861 assertions, 0 failures`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc`
  - passed
- Root harness: not run - isolated micro-slice.
