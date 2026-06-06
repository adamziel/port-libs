# Pandoc Upstream Runner Dependency Audit 2026-06-06

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260606T012647Z`.

Accepted base: `e4fc45845f8fab8d74e7fa5d1f40c3f833e8ee9c`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Stack command, Word, LibreOffice, `zip`/`unzip`, external template
engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff renderer,
media player, online conversion service, online sanitizer, live provider test,
or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `e4fc45845f8fab8d74e7fa5d1f40c3f833e8ee9c`.
- Current Pandoc lane evidence records 2,276 upstream test/data/benchmark
  artifacts inspected, 1,594 mapped native PHP checks in
  `UPSTREAM_TEST_MANIFEST.json`, and 1,142 lane PHP PASS cases with 0 recorded
  failures in `lane-status.json` before this slice.
- Local cache/file searches found no usable hydrated Pandoc checkout, no
  `pandoc.cabal`, no `pandoc-lua-engine.cabal`, no `cabal.project`, and no
  `cabal.project.freeze` under the accessible upstream cache/worktree paths.
- Pinned upstream source at
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` was read only as source text for
  `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
  `cabal.project`, `test/test-pandoc.hs`, and
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`.

## Runner Dependency Closure

The previous local audit correctly required a hydrated checkout, Cabal project
files, exact source-repository pins, runner source/golden fixtures, runner
entry-point semantics, test-suite type/buildability, direct build-depends,
other-modules, default-language, executable options, and
`pandoc-lua-engine` HsLua library dependencies before any non-mutating Cabal
solver/build plan can be trusted.

This slice fixes a package-relative Cabal semantics gap in that audit. The
pinned upstream `pandoc-lua-engine/pandoc-lua-engine.cabal` declares the Lua
runner stanza with:

- `test-suite test-pandoc-lua-engine`
- `main-is: test-pandoc-lua-engine.hs`
- `hs-source-dirs: test`

That `hs-source-dirs` value is relative to the nested
`pandoc-lua-engine` package directory. The audit must therefore accept `test`
in the Cabal stanza while separately requiring the resolved entry source file
at `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`. The stale repo-relative
value `pandoc-lua-engine/test` is now rejected before a non-mutating Cabal plan
is marked ready.

## Dependency-Backlog Decision

No new native PHP conversion support component is activated by this audit. The
blocker remains the upstream Haskell runner/build closure, not a missing
Pandoc-local format primitive.

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

- Red-first focused test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Failed before implementation with `1 test files, 187 assertions,
    13 failures` because the implementation still expected the stale
    `pandoc-lua-engine/test` Cabal source directory.
- Focused test after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Passed with `1 test files, 351 assertions, 0 failures`.
- Focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - Passed with `21 test files, 15865 assertions, 0 failures`.
- PHP syntax check:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php && php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - Passed with no syntax errors.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Whitespace check:
  `git diff --check -- lanes/pandoc`
  - Passed.
- Example smoke: not run - no example added or changed.
- Root harness: not run - isolated micro-slice.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present. Then
record a non-mutating Cabal solver/build plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`, including package-relative `hs-source-dirs`,
runner source/golden fixture roots, and the `doclayout`, `typst-symbols`,
`typst-hs`, `texmath`, and `citeproc` Git source-repository pins. Only after
that plan is stable should a separate runner slice attempt any bounded Haskell
test executable build or focused upstream runner execution.
