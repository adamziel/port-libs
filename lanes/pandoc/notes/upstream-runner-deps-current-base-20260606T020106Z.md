# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T020106Z`.

Accepted base: `33b1117fe7421aae4335ab937bcc9d284c23dffc`.

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
  `33b1117fe7421aae4335ab937bcc9d284c23dffc`.
- Current Pandoc lane evidence records 2,276 upstream test/data/benchmark
  artifacts inspected, 1,600 mapped native PHP checks in
  `UPSTREAM_TEST_MANIFEST.json`, and 1,149 lane PHP PASS cases with 0 recorded
  failures before this slice.
- Local cache/file searches found no usable hydrated Pandoc checkout, no
  `pandoc.cabal`, no `pandoc-lua-engine.cabal`, no `cabal.project`, and no
  `cabal.project.freeze` under the accessible upstream cache/worktree paths.
- `ghc --numeric-version` reported `9.10.3`; `cabal --numeric-version`
  reported `3.12.1.0`; `stack` and `pandoc` were not found on `PATH`.
- Pinned upstream source at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` was inspected only as source
  text for the Haskell runner entry-point shape.

## Implemented Audit Tightening

`UpstreamRunnerDependencyAudit` now requires the pinned `test/test-pandoc.hs`
entry point to keep loading the major Tasty groups that make the Cabal
`other-modules` closure meaningful before a non-mutating Cabal plan can be
marked ready.

The static entry-source semantics now include the upstream shared helper,
media bag, XML, DOCX/ODT/EPUB reader, and DOCX/RST/BBCode writer group calls,
in addition to the previously checked command, Markdown reader/writer, Native
writer, locale, `--emulate`, `noEngine`, and `defaultMain` semantics. A
hydrated-looking checkout that still declares the modules in Cabal metadata but
omits these Tasty group calls from `test-pandoc.hs` is blocked with
`missing runner entry point source semantics` before any Cabal solver/build
command is considered.

This closes a static-audit gap where package metadata could remain complete
while the runner executable no longer exercised the selected source/golden
reader and writer groups the lane uses for upstream denominator evidence.

## Dependency-Backlog Decision

No new native PHP conversion support component is needed. This slice reuses the
existing `UpstreamRunnerDependencyAudit` support row and adds one bounded
native audit case. Full upstream runner parity remains blocked by the missing
hydrated Pandoc checkout and Haskell/Cabal build closure, not by a missing
local document-format primitive.

Existing bounded support rows remain the correct lane-local dependency path for
real conversion coverage:

- `pandoc-shared-zip-package-core`
- `pandoc-opc-xml-relationships-core`
- `pandoc-xml-html5-dom-core`
- `pandoc-doctemplates-core`
- `pandoc-yaml-metadata-core`
- `pandoc-citation-csl-core`
- `pandoc-bibtex-csl-core`
- `pandoc-docx-openxml-core`
- `pandoc-epub3-package-core`
- `pandoc-odf-open-document-core`
- `pandoc-legacy-doc-cfb-core`
- `pandoc-math-tex-conversion-core`
- `pandoc-syntax-highlighting-core`
- `pandoc-charset-unicode-width-core`
- `pandoc-table-geometry-core`
- `pandoc-archive-compression-streams`
- `pandoc-pdf-engine-handoff-core`

## Non-Overlap

This patch only changes `UpstreamRunnerDependencyAudit` and its focused audit
test. It deliberately avoids DOCX/OpenXML, EPUB3, ODT/OpenDocument, archive
compression, charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
support-library surfaces.

## Verification

- Baseline focused test before this slice:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 351 assertions, 0 failures`
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 375 assertions, 0 failures`
  - PASS cases: `24`
  - Focused delta from previous accepted upstream-runner audit baseline:
    `+1` PASS case / `+24` assertions
- Focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `21 test files, 16009 assertions, 0 failures`
- Focused PASS-line count:
  `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - `1137`
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

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry
files, exact source-repository pins, package flags, solver constraints, full
runner `other-modules`, selected source/golden artifacts, runner entry-source
semantics, `ghc`, and `cabal`. Keep actual Cabal and Haskell runner execution
as a separate explicitly authorized slice after a non-mutating plan is
available.
