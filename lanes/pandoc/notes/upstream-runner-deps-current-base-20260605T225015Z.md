# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T225015Z`.

Accepted base: `718fd27c4bd5c5cf3bb2a77e5061b76a630e07d5`.

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
  `718fd27c4bd5c5cf3bb2a77e5061b76a630e07d5`.
- No Pandoc Cabal checkout was present under this lane worktree or the
  bounded `/home/claude/port-libs/.upstream-cache` scan.
- `ghc --numeric-version` reported `9.10.3`.
- `cabal --numeric-version` reported `3.12.1.0`.
- The prior accepted upstream-runner dependency audit baseline for
  `UpstreamRunnerDependencyAuditTest.php` was `1 test files, 301 assertions,
  0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records the full pinned
`test:test-pandoc` Cabal `other-modules` closure from upstream
`pandoc.cabal` at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, not just the
selected smoke modules used by earlier PHP fixture slices.

The closure now includes the pinned runner helper, shared, media bag, XML,
reader, and writer test modules such as `Tests.Readers.Docx` and
`Tests.Writers.BBCode`. A hydrated-looking checkout that omits one of those
modules from the Cabal stanza is blocked with `missing Cabal runner
other-modules` before any non-mutating Cabal solver/build plan can be marked
ready.

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
semantics, `ghc`, and `cabal`. Keep actual Cabal and Haskell runner execution
as a separate explicitly authorized slice.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers,
XML/HTML5 DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry,
math/TeX, PDF handoff planning, archive compression streams,
charset/Unicode support, doctemplates, syntax highlighting, or legacy
DOC/CFB behavior. It maps one additional upstream-runner dependency audit
case and one PHP PASS case only.

## Verification

- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 316 assertions, 0 failures`
  - PASS cases: `20`
  - Focused delta from previous accepted upstream-runner audit baseline:
    `+1` PASS case / `+15` assertions
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
