# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T221855Z`.

Accepted base: `d7fc0d324d486c7743668feb7d0e673859bb6b23`.

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
  `d7fc0d324d486c7743668feb7d0e673859bb6b23`.
- The previous accepted upstream-runner audit baseline for
  `UpstreamRunnerDependencyAuditTest.php` was `1 test files, 291 assertions,
  0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now requires the selected Lua runner
`other-modules` source files as runner artifacts before marking a checkout
ready for a non-mutating Cabal plan:

- `pandoc-lua-engine/test/Tests/Lua.hs`
- `pandoc-lua-engine/test/Tests/Lua/Module.hs`
- `pandoc-lua-engine/test/Tests/Lua/Reader.hs`
- `pandoc-lua-engine/test/Tests/Lua/Writer.hs`

This closes a static dependency-planning gap where the Cabal stanza could list
`Tests.Lua.Reader` or its sibling Lua test modules while the checkout only had
the `pandoc-lua-engine/test` directory. Such a checkout is now blocked with
`missing upstream runner source/golden fixture artifacts` before any Cabal
solver/build/test command is considered.

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
constraints, runner direct build-depends, runner `other-modules`, runner
default-language, runner executable options, selected `pandoc-lua-engine`
library HsLua module build-depends, `ghc`, and `cabal`. Keep actual Cabal and
Haskell runner execution as a separate explicitly authorized slice.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers,
XML/HTML5 DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry,
math/TeX, PDF handoff planning, archive compression streams,
charset/Unicode support, doctemplates, syntax highlighting, or legacy
DOC/CFB behavior. It maps one additional upstream-runner dependency audit
case and one PHP PASS case only.

## Verification

- Focused baseline before implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 291 assertions, 0 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 301 assertions, 0 failures`
  - PASS cases: `19`
  - Focused delta from previous accepted upstream-runner audit baseline:
    `+1` PASS case / `+10` assertions
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
  - passed with no output
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
