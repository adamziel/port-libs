# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T155414Z`.

Accepted base: `205bce50edd3fe6b394151a64344ea9de39b3aa1`.

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
  `205bce50edd3fe6b394151a64344ea9de39b3aa1`.
- `/home/claude/port-libs/.upstream-cache/pandoc` is not present, and a
  bounded filename scan under `/home/claude/port-libs/.upstream-cache` found no
  `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- Baseline focused audit test passed before this slice with
  `1 test files, 128 assertions, 0 failures`.
- The red-first focused test failed because
  `UpstreamRunnerDependencyAudit::expectedRunnerArtifacts()` and
  `runnerArtifactClosure` did not exist, and a package-only hydrated fixture
  could still be marked ready for a non-mutating Cabal plan.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records `runnerArtifactClosure` and blocks
`readyForNonMutatingCabalPlan` until selected upstream runner source/golden
artifacts exist with the expected file or directory kind:

- `test/Tests/Command.hs`
- `test/Tests/Readers/Markdown.hs`
- `test/Tests/Writers/Markdown.hs`
- `test/Tests/Writers/Native.hs`
- `test/command`
- `test/tables`
- `test/testsuite.txt`
- `test/testsuite.native`
- `test/markdown-reader-more.txt`
- `test/markdown-reader-more.native`
- `test/html-reader.html`
- `test/html-reader.native`
- `pandoc-lua-engine/test`
- `data`

This closes a dependency-planning gap where Cabal project/package closure,
source-repository pins, test-suite entry points, direct dependencies,
executable options, buildable state, `ghc`, and `cabal` could all be present
while the runner source modules and golden fixtures needed for meaningful
Pandoc Tasty execution were absent.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with
`cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`,
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs`, the selected runner source
modules and fixture roots listed above, and the existing cabal.project
source-repository pins. Only then record a non-mutating Cabal solver/build plan
for `test:test-pandoc` and `test:test-pandoc-lua-engine`.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and one PHP PASS case only.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 128 assertions, 0 failures`
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - failed before implementation because the audit did not expose runner
    artifact closure and incorrectly allowed a package-only hydrated fixture.
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
  - `1 test files, 152 assertions, 0 failures`
  - PASS cases: `9`
- Focused Pandoc lane tests:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `21 test files, 13045 assertions, 0 failures`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc`
  - passed
- Root harness: not run - isolated micro-slice.
