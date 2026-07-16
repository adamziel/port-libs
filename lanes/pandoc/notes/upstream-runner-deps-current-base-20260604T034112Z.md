# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T034112Z`.

Accepted base: `59ad35343f0b979589ac3a508925c996eae4a547`.

This is an upstream-runner dependency audit slice. It adds a bounded native PHP
audit helper so the lane can classify runner dependency gates without executing
Pandoc, Cabal, Haskell test binaries, Word, LibreOffice, `zip`/`unzip`, external
template engines, TeX/PDF engines, or online services as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- A lane/cache scan found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, `cabal.project.freeze`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` under
  `lanes/pandoc` or `/home/claude/port-libs/.upstream-cache`.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.
- The accepted lane still records 2,276 upstream test/data/benchmark artifacts
  inspected and 737 focused conversion/support checks mapped. This slice does
  not change the upstream mapped denominator.

## Implementation

Added `PortLibs\Pandoc\PandocUpstreamRunnerDependencyAudit`, a pure PHP helper
that evaluates candidate local checkout roots and supplied Haskell tool-version
evidence. The helper reports:

- `blocked_missing_upstream_source` when no single root contains
  `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
  `test/test-pandoc.hs`, and
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`;
- `blocked_missing_haskell_tools` when source truth is present but `ghc` or
  `cabal` evidence is absent;
- `ready_for_dependency_plan` only when source truth plus `ghc` and `cabal`
  evidence are present, with the next action explicitly limited to a
  non-mutating Cabal dependency plan;
- an unpinned-plan risk when a checkout lacks `cabal.project` or
  `cabal.project.freeze`.

The tests use temporary synthetic checkout directories. They do not inspect
secret-bearing configuration and do not mutate the shared checkout or upstream
cache.

## Dependency Closure

No new conversion support component is needed for the current PHP lane. The
existing bounded components remain the support-library path for richer native
conversion: ZIP/OPC package primitives, doctemplates, YAML metadata, CSL,
BibTeX/BibLaTeX, DOCX/OpenXML, EPUB/ODT, legacy DOC/CFB, TeX/MathML, syntax
highlighting, Unicode width/charset, table geometry, archive/compression
streams, and PDF-engine handoff planning.

The full upstream Pandoc runner remains blocked by missing local upstream
source truth, not by a missing Pandoc-local PHP component. Before claiming
runner dependency closure, hydrate the Pandoc upstream checkout at manifest
commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, restore the Cabal package
files and Tasty entrypoints, and record a non-mutating Cabal dependency plan
for `test-pandoc` and `test-pandoc-lua-engine`. Any Haskell executable build or
runner execution should remain a separate explicitly authorized runner slice.

## Verification

- `php tools/run-tests.php lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`
  passed: 1 file, 30 assertions, 0 failures, 4 PASS lines.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 8 files, 2868
  assertions, 0 failures, 305 PASS lines.
- `php -l lanes/pandoc/src/PandocUpstreamRunnerDependencyAudit.php` passed.
- `php -l lanes/pandoc/tests/PandocUpstreamRunnerDependencyAuditTest.php`
  passed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
