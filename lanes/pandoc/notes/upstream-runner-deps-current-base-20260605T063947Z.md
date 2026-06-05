# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T063947Z`.

Accepted base: `ba06d5cb86a13b5670fe5b301e6ef43c8eb66777`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`, external template
engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff renderer,
media player, online conversion service, online sanitizer, or other external
converter was executed as progress. Only tool-availability probes and
lane-local PHP tests were run.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `ba06d5cb86a13b5670fe5b301e6ef43c8eb66777`.
- Current Pandoc lane evidence records 2,276 upstream test/data/benchmark
  artifacts inspected, 1,174 mapped native PHP checks in
  `UPSTREAM_TEST_MANIFEST.json`, and 713 lane PHP PASS cases with 0 recorded
  failures in `lane-status.json`.
- Searches under `/home/claude/port-libs/.upstream-cache` found no directory
  whose name contains `pandoc`.
- Filename searches under `/home/claude/port-libs/.upstream-cache` and this
  isolated worktree found no `pandoc.cabal`, `pandoc-lua-engine.cabal`,
  `cabal.project`, `cabal.project.freeze`, `test-pandoc.hs`, or
  `test-pandoc-lua-engine.hs` source files.
- `ghc` is available as version 9.10.3 and `cabal-install` is available as
  version 3.12.1.0. `stack` and `pandoc` were not found on `PATH`.
- The raw-source Cabal closure recorded by
  `upstream-runner-deps-current-base-20260604T170433Z.md` remains the accepted
  lane-local source truth for the runner dependency shape at upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Runner Dependency Closure

The upstream runner gate remains outside native PHP conversion support. The
pinned `test-pandoc` runner is a Haskell `exitcode-stdio-1.0` Tasty executable
with `main-is: test-pandoc.hs`; its direct test closure includes the local
`pandoc` library plus `Diff`, `Glob`, `bytestring`, `containers`, `directory`,
`doctemplates`, `filepath`, `mtl`, `pandoc-types`, `process`, `tasty`,
`tasty-golden`, `tasty-hunit`, `tasty-quickcheck`, `text`, `temporary`, `time`,
`xml`, and `zip-archive`. Its `--emulate` path calls `convertWithOpts
noEngine`, so command-golden parity requires the compiled upstream test
executable rather than static fixture reads alone.

The pinned `test-pandoc-lua-engine` runner is also a Tasty executable. Its
direct test closure includes the local `pandoc-lua-engine` library plus
`bytestring`, `directory`, `data-default`, `exceptions`, `filepath`, `hslua`,
`pandoc`, `pandoc-types`, `tasty`, `tasty-golden`, `tasty-hunit`, `tasty-lua`,
and `text`. The Lua-engine library closure also brings HsLua module packages,
`lpeg`, and `pandoc-lua-marshal`, with optional `hslua-repl` when the `repl`
flag is enabled.

The pinned `cabal.project` is part of the runner dependency closure. It lists
local packages `.`, `pandoc-lua-engine`, `pandoc-server`, and `pandoc-cli`;
enables Pandoc flags `+embed_data_files +http`; and pins Git
source-repository dependencies for `doclayout`, `typst-symbols`, `typst-hs`,
`texmath`, and `citeproc`. A future runner plan must resolve Hackage packages
plus those exact Git pins before any bounded Haskell runner execution can be
claimed.

## Current Activation Decision

No safe local upstream-runner build step is available in this worktree. The
toolchain binaries are present, but the hydrated Pandoc checkout and its
`cabal.project`, `pandoc.cabal`, and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files are absent. Running a Cabal
solver or build from this isolated lane would require hydrating or fetching the
broad upstream checkout plus resolving and building the Haskell dependency
graph before a non-mutating solver/build plan could be recorded.

This keeps the full upstream runner gate open. It does not block accepted
native PHP conversion slices for Markdown/HTML, XML/HTML5 DOM, ZIP/OPC, YAML,
CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX, PDF handoff planning,
archive compression streams, charset/Unicode support, doctemplates, syntax
highlighting, or legacy DOC/CFB.

## Dependency-Backlog Decision

No new native PHP support component is activated by this audit. The blocker is
the upstream Haskell runner/build closure, not a missing Pandoc-local PHP
format primitive. Existing bounded support rows remain the correct lane-local
dependency path for real conversion coverage:

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

This audit deliberately avoids native support-library implementation slices,
including current DOCX/OpenXML, EPUB3, ODT/OpenDocument, archive compression,
charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table-geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
surfaces. It claims no additional native mapping, no PHP PASS growth, and no
upstream-runner parity.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present. Then
record a non-mutating Cabal solver/build plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`, including how the `doclayout`, `typst-symbols`,
`typst-hs`, `texmath`, and `citeproc` Git source-repository pins are resolved.
Only after that plan is stable should a separate runner slice attempt any
bounded Haskell test executable build or focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- Focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `20 test files, 8,152 assertions, 0 failures`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
