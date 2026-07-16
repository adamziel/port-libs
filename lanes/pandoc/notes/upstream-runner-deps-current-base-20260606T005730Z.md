# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T005730Z`.

Accepted base: `ff7d31e1397095949e33524eafeb5b7160ae8790`.

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
  `ff7d31e1397095949e33524eafeb5b7160ae8790`.
- No local Pandoc checkout was present under
  `/home/claude/port-libs/.upstream-cache` in this environment.
- Static source truth was limited to pinned upstream raw Cabal/project files at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`; no upstream runner was built or
  executed.
- The prior accepted upstream-runner dependency audit baseline for
  `UpstreamRunnerDependencyAuditTest.php` was `1 test files, 326 assertions,
  0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now skips Cabal project `if` / `elif` /
`else` branch bodies before extracting the unconditional runner package,
flag, solver-constraint, and source-repository closure.

This closes a static-audit false blocker against the pinned upstream
`cabal.project`, whose `if arch(wasm32)` branch disables tests, flips
`pandoc:http` off, and adds WASM-only source-repository packages. Those branch
fields must not override the unconditional native runner dependency closure
used to decide whether a non-mutating Cabal plan can be prepared.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry
files, exact unconditional source-repository pins, package flags, solver
constraints, full runner `other-modules`, selected source/golden artifacts,
runner entry-source semantics, `ghc`, and `cabal`. Conditional Cabal project
branches should be evaluated only in a separate target-specific audit slice
before any solver/build command.

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
  - `1 test files, 326 assertions, 0 failures`
- Red-first focused test after adding the Cabal project conditional fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Failed with `1 test files, 327 assertions, 1 failures` because
    `readyForNonMutatingCabalPlan` was `false`.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 335 assertions, 0 failures`
  - PASS cases: `22`
  - Focused delta from previous accepted upstream-runner audit baseline:
    `+1` PASS case / `+9` assertions
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
