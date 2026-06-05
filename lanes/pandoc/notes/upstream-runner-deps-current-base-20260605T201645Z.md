# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T201645Z`.

Accepted base: `b7c69a82698c2416756edbae2bb3a28381b7f166`.

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
  `b7c69a82698c2416756edbae2bb3a28381b7f166`.
- Baseline focused audit test before this slice passed with
  `1 test files, 258 assertions, 0 failures`.
- The red-first focused checks failed with `1 test files, 260 assertions,
  2 failures`: a Cabal `--` comment line could swallow the real dependency on
  the next line after the parser collapsed newlines, and a commented-out
  runner `other-modules` entry or `ghc-options` token could be counted as
  present.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now strips Cabal line comments before resolving
field token lists used by the runner dependency audit:

- `import` common-stanza references.
- `build-depends`.
- `ghc-options`.
- `hs-source-dirs`.
- `default-language`.
- `other-modules`.

This keeps a hydrated-looking checkout from being marked ready for a
non-mutating Cabal plan when required runner options or modules only appear in
comments. It also prevents false blockers when a comment line appears before a
real dependency in a multiline Cabal field.

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
library HsLua module build-depends, `ghc`, and `cabal`. Preserve the
comment-normalized Cabal field audit before recording any non-mutating
solver/build plan for `test:test-pandoc` and `test:test-pandoc-lua-engine`.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and two PHP PASS cases only.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 258 assertions, 0 failures`
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - failed before implementation with `1 test files, 260 assertions,
    2 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 269 assertions, 0 failures`
  - PASS cases: `16`
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
