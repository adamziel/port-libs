# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T023326Z`.

Accepted base: `7e95b10d11f5767b21764022fd15eea3308c3829`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell
test binary, Stack command, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`,
external template engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser
renderer, roff renderer, media player, online conversion service, online
sanitizer, live provider test, or other external converter was executed as
progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `7e95b10d11f5767b21764022fd15eea3308c3829`.
- The focused upstream-runner audit baseline before edits passed:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported `1 test files, 375 assertions, 0 failures`.
- The local upstream cache path used by earlier notes was not available in this
  worktree, so the pinned raw upstream Cabal files were read as static source
  truth at commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now records selected direct
`build-depends` version constraints for both runner targets:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`

The audit already checked that the direct dependency names were present. This
slice closes the remaining static gap where a hydrated-looking checkout could
keep dependency names such as `pandoc-types`, `zip-archive`, `hslua`, and
`tasty-lua`, but strip or loosen the pinned lower/upper bounds from the
upstream Cabal test-suite stanzas before a non-mutating Cabal plan.

The report now exposes:

- `expectedDependencyConstraints`
- per-target `dependencyConstraints`
- `mismatchedDependencyConstraints`

Stale or missing selected bounds now block readiness with
`mismatched Cabal runner direct build-depends constraints`.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required
Cabal project/package files, runner entry files, selected source/golden
fixture artifacts, exact source-repository pins, package flags, solver
constraints, runner direct build-depends and selected version constraints,
runner `other-modules`, runner default-language, runner executable options,
selected `pandoc-lua-engine` library HsLua module build-depends, `ghc`, and
`cabal`. Keep any Cabal solver/build plan and Haskell runner execution as a
separate explicitly authorized slice.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML, EPUB3, ODT/OpenDocument, archive compression, charset/Unicode,
syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX, table geometry, math/TeX, PDF
handoff, XML/HTML5 DOM, and legacy DOC/CFB support-library surfaces.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 375 assertions, 0 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 399 assertions, 0 failures`
  - PASS cases: `25`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+24` assertions
- PHP syntax:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected in lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
- PHP syntax:
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected in lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Passed with no output.
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native audit against
that checkout before any Cabal solver/build command. If the static audit is
ready, record a non-mutating Cabal plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`; keep Haskell runner execution out of this
dependency audit slice.
