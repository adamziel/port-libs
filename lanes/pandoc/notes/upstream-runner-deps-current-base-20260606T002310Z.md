# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T002310Z`.

Accepted base: `3770422869cdd12a822ef3211ffa8f11325ead39`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell
test binary, `stack`, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`,
external template engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser
renderer, roff renderer, online conversion service, online sanitizer, or
other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `3770422869cdd12a822ef3211ffa8f11325ead39`.
- No Pandoc Cabal checkout was present under this lane worktree or the bounded
  `/home/claude/port-libs/.upstream-cache` scan.
- The prior accepted upstream-runner dependency audit baseline for
  `UpstreamRunnerDependencyAuditTest.php` was `1 test files, 316 assertions,
  0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now skips Cabal `if` / `elif` / `else` branch
bodies while extracting the unconditional runner test-suite and library stanza
closure.

This closes a static-audit false negative where optional conditional
`build-depends`, `ghc-options`, or `other-modules` fields after a valid
runner stanza could overwrite the unconditional fields in the PHP parser and
make a hydrated-looking checkout appear blocked before any non-mutating Cabal
plan. The audit now keeps the base stanza closure stable and does not count
optional conditional dependencies or modules as unconditional runner closure.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry
files, exact source-repository pins, package flags, solver constraints, full
runner `other-modules`, selected source/golden artifacts, runner entry-source
semantics, `ghc`, and `cabal`. If the hydrated upstream Cabal files contain
required dependencies only behind conditionals, evaluate and record those
conditions in a separate non-mutating audit slice before any solver/build
command.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers,
XML/HTML5 DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry,
math/TeX, PDF handoff planning, archive compression streams,
charset/Unicode support, doctemplates, syntax highlighting, or legacy
DOC/CFB behavior. It maps one additional upstream-runner dependency audit
case and one PHP PASS case only.

## Verification

- Baseline focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 316 assertions, 0 failures`
- Red-first focused test after adding the conditional-block fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Failed with `1 test files, 317 assertions, 1 failures` because
    `readyForNonMutatingCabalPlan` was `false`.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 326 assertions, 0 failures`
  - PASS cases: `21`
  - Focused delta from previous accepted upstream-runner audit baseline:
    `+1` PASS case / `+10` assertions
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
