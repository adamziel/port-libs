# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T050757Z`.

Accepted base: `dffb68d11b769f872d4da32f21b819394fad38ff`.

This is an upstream-runner dependency audit slice, not a native document
conversion behavior slice. No Pandoc binary, Cabal solver/build/test command,
Haskell test binary, benchmark executable, Stack command, Word, LibreOffice,
`zip`/`unzip`, `tar`, `lz4`, external template engine, TeX/PDF engine,
MathJax, KaTeX, Typst, browser renderer, roff renderer, media player, online
conversion service, online sanitizer, live provider test, or other external
converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `dffb68d11b769f872d4da32f21b819394fad38ff`.
- The focused upstream-runner audit baseline before edits passed:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported `1 test files, 436 assertions, 0 failures`.
- Static source truth was inspected from the pinned upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`: `pandoc.cabal` and
  `benchmark/benchmark-pandoc.hs`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records benchmark entry-source semantics
for the pinned `benchmark:benchmark-pandoc` component before a non-mutating
Cabal plan can be marked ready.

The previous audit checked benchmark Cabal metadata and required source/data
artifact presence. This slice closes the remaining static gap where a checkout
could provide a placeholder `benchmark/benchmark-pandoc.hs` file while still
passing benchmark artifact and Cabal component closure.

The audit now verifies that the benchmark source still carries the relevant
pinned behavior shape:

- Pandoc conversion registry and MIME/media imports.
- `Test.Tasty.Bench` benchmark harness import.
- bibliography-only format exclusions for `bibtex`, `biblatex`, and `csljson`.
- `getReader` and `getWriter` lookup through `FlavoredFormat`.
- default template compilation before reader round-trip benchmarks.
- text and bytestring reader benchmark paths.
- `test/lalune.jpg`, `test/movie.jpg`, and `test/testsuite.txt` fixture reads.
- media insertion before writer benchmarks.
- Markdown testsuite parsing into a forced Pandoc AST.
- `defaultMain` with `writers` and `readers` benchmark groups.

The focused test stubs `benchmark/benchmark-pandoc.hs` while leaving the Cabal
benchmark stanza, direct dependencies, executable options, default language,
and benchmark artifacts otherwise valid. The audit now blocks that tree with
`missing benchmark entry point source semantics` instead of treating it as
ready for Cabal planning.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, runner source/golden fixture
artifacts, benchmark source/data artifacts, benchmark entry-source semantics,
derived other-module Haskell source artifacts, exact source-repository pins,
package flags, solver constraints, direct runner and benchmark `build-depends`
constraints, runner `other-modules`, default languages, executable options,
selected `pandoc-lua-engine` library HsLua module `build-depends`, `ghc`, and
`cabal`. Keep any Cabal solver/build plan, Haskell runner execution, and
benchmark execution as separate explicitly authorized slices.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive compression,
charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX, table
geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 436 assertions, 0 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 460 assertions, 0 failures`
  - PASS cases: `28`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+24` assertions
- PHP lint:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected`
- PHP lint:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - passed with no output
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against that checkout before any Cabal solver/build command. If the static
audit is ready, record a non-mutating Cabal plan for `test:test-pandoc`,
`test:test-pandoc-lua-engine`, and `benchmark:benchmark-pandoc`; keep Haskell
runner and benchmark execution out of this dependency audit slice.
