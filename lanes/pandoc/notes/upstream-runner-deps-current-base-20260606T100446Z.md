# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T100446Z`.

Accepted base: `1420277bc6031a522c9261ef52aa1ee5c7c3d325`.

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
  `1420277bc6031a522c9261ef52aa1ee5c7c3d325`.
- A bounded filename scan under `/home/claude/port-libs/.upstream-cache` found
  no `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` files.
- `command -v ghc` and `command -v cabal` found `/usr/bin/ghc` and
  `/usr/bin/cabal`; `command -v stack` returned no path.
- The focused upstream-runner audit baseline before edits passed:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  reported `1 test files, 504 assertions, 0 failures`.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now parses Cabal `build-tool-depends` and
legacy `build-tools` fields for:

- `test:test-pandoc`
- `test:test-pandoc-lua-engine`
- `benchmark:benchmark-pandoc`

The audit already required the pinned runner and benchmark package metadata,
direct dependencies, dependency constraints, default languages, executable
options, other-modules, source/golden artifacts, entry-source semantics,
unexpected mixin rejection, and non-empty fixture artifacts.

This slice closes the next static dependency gap: a hydrated-looking checkout
can no longer be marked ready for a non-mutating Cabal plan when a runner or
benchmark stanza adds executable build-tool dependencies such as `doctest`,
`hspec-discover`, `hslua-cli`, `tasty-discover`, or legacy tools such as
`cpphs`, `happy`, or `alex`.

The Cabal parser now honors indentation before field matching, so
`build-tool-depends` continuation rows like `doctest:doctest >= 0.20` are
treated as dependency entries instead of being misread as nested Cabal fields.
Imported `common` stanza build-tool fields are merged into the concrete runner
or benchmark closure before readiness is decided.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with the required Cabal
project/package files, runner entry files, exact source-repository pins,
package flags, solver constraints, runner direct `build-depends` constraints,
runner `other-modules`, runner default-language, runner executable options,
selected `pandoc-lua-engine` library HsLua module `build-depends`, non-empty
runner source/golden fixture artifacts, non-empty benchmark source/data
artifacts, no unexpected runner or benchmark mixins, no runner or benchmark
build-tool dependencies, `ghc`, and `cabal`. Keep any Cabal solver/build plan,
Haskell runner execution, and benchmark execution as separate explicitly
authorized slices.

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit`, its focused audit
test, lane status/manifest fields, and this lane note. It deliberately avoids
DOCX/OpenXML conversion behavior, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Baseline focused test before edits:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 504 assertions, 0 failures`
- Red-first focused test after adding the build-tool dependency fixture:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 505 assertions, 1 failure`
  - Failure: a fixture with runner and benchmark `build-tool-depends` /
    `build-tools` was still marked ready.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 532 assertions, 0 failures`
  - PASS cases: `31`
  - Focused delta from current accepted upstream-runner audit baseline:
    `+1` PASS case / `+28` assertions
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

## Next Activation Gate

Hydrate the pinned upstream Pandoc checkout and run this native static audit
against that checkout before any Cabal solver/build command. If the static
audit is ready, record a non-mutating Cabal plan for `test:test-pandoc`,
`test:test-pandoc-lua-engine`, and `benchmark:benchmark-pandoc`; keep Haskell
runner and benchmark execution out of this dependency audit slice.
