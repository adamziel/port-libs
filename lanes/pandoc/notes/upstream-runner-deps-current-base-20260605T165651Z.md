# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T165651Z`.

Accepted base: `5417c5c77ed7abafc4aa8f6b8401abfd58981dad`.

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
  `5417c5c77ed7abafc4aa8f6b8401abfd58981dad`.
- `/home/claude/port-libs/.upstream-cache` still has no local hydrated Pandoc
  checkout files named `pandoc.cabal`, `pandoc-lua-engine.cabal`,
  `cabal.project`, `test-pandoc.hs`, or `test-pandoc-lua-engine.hs`.
- Baseline focused audit test before this slice passed with
  `1 test files, 168 assertions, 0 failures`.
- The pinned raw upstream entry sources at commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` show that
  `test/test-pandoc.hs` sets `setLocaleEncoding utf8`, supports a `--emulate`
  command path through `convertWithOpts noEngine`, enters the upstream `test`
  directory, passes `getExecutablePath` into the Tasty tree, and runs selected
  command/reader/writer groups with `defaultMain`. The pinned Lua engine entry
  source enters `test`, runs `defaultMain tests`, and groups Lua filter,
  module, custom writer, and custom reader tests.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records `runnerEntrySourceClosure` for the
two required runner entry files:

- `test/test-pandoc.hs`
- `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`

The audit blocks `readyForNonMutatingCabalPlan` if the main Pandoc runner entry
loses any of these pinned source semantics:

- UTF-8 locale setup.
- `--emulate` command-runner path.
- `convertWithOpts noEngine` command execution.
- `inDirectory "test"` runner fixture cwd.
- `getExecutablePath` handoff to old command tests.
- `defaultMain $ tests fp`.
- selected `Tests.Command`, Markdown reader, Native writer, and Markdown writer
  Tasty groups.

The audit also blocks readiness if the Lua engine runner entry loses:

- `withCurrentDirectory "test"`.
- `defaultMain tests`.
- `testGroup "pandoc Lua engine"`.
- Lua filter, Lua module, custom writer, and custom reader Tasty groups.

This closes a dependency-planning gap where Cabal project/package closure,
source-repository pins, runner fixture roots, file hashes, executable options,
and buildable `exitcode-stdio-1.0` test-suite stanzas could all be present
while the entry source no longer invoked the intended upstream Tasty runner
paths.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, selected source/golden fixture
artifacts, exact source-repository pins, and the entry-source semantics above.
Only then record a non-mutating Cabal solver/build plan for `test:test-pandoc`
and `test:test-pandoc-lua-engine`.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and one PHP PASS case only.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 168 assertions, 0 failures`
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 194 assertions, 0 failures`
  - PASS cases: `11`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc`
  - passed
- Root harness: not run - isolated micro-slice.
