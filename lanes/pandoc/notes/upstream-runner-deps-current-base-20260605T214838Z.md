# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T214838Z`.

Accepted base: `90854239e2675032c2ad9d4f94cc8a69f5df5884`.

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
  `90854239e2675032c2ad9d4f94cc8a69f5df5884`.
- The previous accepted upstream-runner audit slice recorded the focused
  `UpstreamRunnerDependencyAuditTest.php` baseline at `1 test files, 269
  assertions, 0 failures`.
- The runner audit already strips Cabal `--` comments for runner fields such
  as `build-depends`, `ghc-options`, `hs-source-dirs`, `default-language`, and
  `other-modules`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now also strips Cabal line comments before
parsing project-level `cabal.project` closure:

- `packages`
- package `flags`
- solver `constraints`
- `source-repository-package` tags
- `source-repository-package` type/location metadata

This closes two static dependency-planning gaps:

- Harmless inline comments on package lists, flags, solver constraints, and
  source-repository metadata no longer create false blockers before a
  non-mutating Cabal plan.
- Commented-out `cabal.project` package or flag metadata no longer counts as
  present and can no longer make a hydrated-looking checkout falsely ready for
  a non-mutating Cabal plan.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds two bounded
native audit cases. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required
Cabal project/package files, runner entry files, selected source/golden
fixture artifacts, exact source-repository pins, package flags, solver
constraints, runner direct build-depends, runner `other-modules`,
runner default-language, runner executable options, selected
`pandoc-lua-engine` library HsLua module build-depends, `ghc`, and `cabal`.
Preserve comment-normalized `cabal.project` and runner-field parsing before
recording any non-mutating solver/build plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers,
XML/HTML5 DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry,
math/TeX, PDF handoff planning, archive compression streams,
charset/Unicode support, doctemplates, syntax highlighting, or legacy
DOC/CFB behavior. It maps two additional upstream-runner dependency audit
cases and two PHP PASS cases only.

## Verification

- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 291 assertions, 0 failures`
  - PASS cases: `18`
  - Focused delta from previous accepted upstream-runner audit baseline:
    `+2` PASS cases / `+22` assertions
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace:
  `git diff --check -- lanes/pandoc`
  - passed with no output
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.
