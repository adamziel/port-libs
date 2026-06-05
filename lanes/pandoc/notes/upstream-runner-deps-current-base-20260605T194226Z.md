# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T194226Z`.

Accepted base: `f281a354364ddf14101a5176b72ed27f0c7958ca`.

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
  `f281a354364ddf14101a5176b72ed27f0c7958ca`.
- A bounded filename scan under `/home/claude/port-libs/.upstream-cache`
  found no `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- The accepted raw-source runner dependency note
  `upstream-runner-deps-current-base-20260604T170433Z.md` records that the
  pinned `pandoc-lua-engine` library closure includes HsLua module packages,
  `lpeg`, and `pandoc-lua-marshal`, plus optional `hslua-repl` behind the
  upstream `repl` flag.
- Baseline focused audit test before this slice passed with
  `1 test files, 235 assertions, 0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now parses Cabal `library` stanzas, including
fields inherited through `common` imports, and records a
`luaEngineLibraryClosure` block for
`pandoc-lua-engine/pandoc-lua-engine.cabal`.

A hydrated-looking checkout is no longer marked
`readyForNonMutatingCabalPlan: true` unless the default
`pandoc-lua-engine` library build-depends include these selected upstream
runner dependencies:

- `hslua-module-doclayout`
- `hslua-module-path`
- `hslua-module-system`
- `hslua-module-text`
- `hslua-module-version`
- `hslua-module-zip`
- `lpeg`
- `pandoc-lua-marshal`

The audit intentionally does not require optional `hslua-repl` because the
accepted upstream note ties it to the optional `repl` flag, not to the default
runner closure.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses
the existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required
Cabal project/package files, runner entry files, selected source/golden fixture
artifacts, exact source-repository pins, package flags, solver constraints,
runner direct build-depends, runner `other-modules`, runner executable
options, `Haskell2010` default-language closure, the selected
`pandoc-lua-engine` library HsLua module build-depends, `ghc`, and `cabal`.
Only then record a non-mutating Cabal solver/build plan for
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
  - `1 test files, 235 assertions, 0 failures`
- Red-first/intermediate focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - failed before final implementation because the audit packet did not yet
    expose `luaEngineLibraryClosure`
  - `1 test files, 227 assertions, 2 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 258 assertions, 0 failures`
  - PASS cases: `14`
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
