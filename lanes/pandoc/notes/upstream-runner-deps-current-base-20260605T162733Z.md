# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T162733Z`.

Accepted base: `cf274bcd9662639c582a7b638303ea4b1facefb6`.

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
  `cf274bcd9662639c582a7b638303ea4b1facefb6`.
- Baseline focused audit test passed before this slice with
  `1 test files, 152 assertions, 0 failures`.
- The red-first focused audit test failed because
  `UpstreamRunnerDependencyAudit` did not expose `requiredFileProvenance`, so
  a ready non-mutating plan had no lane-local SHA-256/byte evidence for the
  exact Cabal project, package, and runner entry-point files it was planned
  from.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records `requiredFileProvenance` for the
five required runner files:

- `cabal.project`
- `pandoc.cabal`
- `pandoc-lua-engine/pandoc-lua-engine.cabal`
- `test/test-pandoc.hs`
- `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`

For each present file the audit records a SHA-256 digest and byte count. Missing
or unreadable files remain in the existing missing required-file closure and
continue to block `readyForNonMutatingCabalPlan`.

This closes a static dependency-planning gap: the existing non-mutating plan
already required package-file hashes to be recorded before any Cabal solver or
build command, but the audit output did not provide those hashes for the
integrator to review.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the five required
files above, selected runner source/golden fixture artifacts, and the existing
`cabal.project` source-repository pins. Then review the recorded file
provenance, package entries, flags, solver constraints, source-repository
types/locations/tags, buildable `exitcode-stdio-1.0` test-suite stanzas, entry
points, direct build-depends, and runner executable options before recording a
non-mutating Cabal solver/build plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`.

## Non-Overlap

This patch does not touch native Markdown/HTML readers or writers, XML/HTML5
DOM, ZIP/OPC, YAML, CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX,
PDF handoff planning, archive compression streams, charset/Unicode support,
doctemplates, syntax highlighting, or legacy DOC/CFB behavior. It maps one
additional upstream-runner dependency audit case and one PHP PASS case only.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 152 assertions, 0 failures`
- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - failed before implementation with `1 test files, 137 assertions,
    2 failures` because `requiredFileProvenance` did not exist.
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 168 assertions, 0 failures`
  - PASS cases: `10`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc`
  - passed
- Root harness: not run - isolated micro-slice.
