# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T050552Z`.

Accepted base: `f7a2c4d50859ee2201e67502670935dceb5a08c7`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`, external template
engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff renderer,
media player, online conversion service, online sanitizer, or other external
converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `f7a2c4d50859ee2201e67502670935dceb5a08c7`.
- Current Pandoc lane evidence records 2,276 upstream test/data/benchmark
  artifacts inspected, 1,114 mapped native PHP checks in
  `UPSTREAM_TEST_MANIFEST.json`, and 639 lane PHP PASS cases with 0 recorded
  failures in `lane-status.json`.
- `/home/claude/port-libs/.upstream-cache/pandoc` and
  `/home/claude/port-libs/.upstream-cache/pandoc-build-port-pandoc` remain
  absent. Filename searches under `/home/claude/port-libs/.upstream-cache` and
  this isolated worktree found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, `cabal.project.freeze`,
  `test-pandoc.hs`, or `test-pandoc-lua-engine.hs` source files.
- `ghc` is available as version 9.10.3 and `cabal-install` is available as
  version 3.12.1.0. `stack` is not on `PATH`.
- Because the local Pandoc checkout is absent, this audit re-read only the
  pinned upstream raw source files at commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:
  `cabal.project`, `pandoc.cabal`,
  `pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`.

## Runner Dependency Closure

The pinned `test-pandoc` runner remains a Haskell `exitcode-stdio-1.0` Tasty
executable with `main-is: test-pandoc.hs` and `hs-source-dirs: test`. Its
direct Cabal closure includes the local `pandoc` library plus `Diff`, `Glob`,
`bytestring`, `containers`, `directory`, `doctemplates`, `filepath`, `mtl`,
`pandoc-types`, `process`, `tasty`, `tasty-golden`, `tasty-hunit`,
`tasty-quickcheck`, `text`, `temporary`, `time`, `xml`, and `zip-archive`.

The `test/test-pandoc.hs` entry point sets locale encoding to UTF-8. In normal
mode it changes into the upstream `test` directory and runs the Tasty tree for
command fixtures, old tests, shared helpers, media bag, XML, writer groups, and
reader groups. With `--emulate`, the same executable acts as a Pandoc command
runner by calling `convertWithOpts noEngine`, so command-golden parity requires
the compiled test executable rather than static source reads alone.

The pinned `test-pandoc-lua-engine` runner remains a Tasty executable with
`main-is: test-pandoc-lua-engine.hs` and `hs-source-dirs: test`. Its direct
test closure includes the local `pandoc-lua-engine` library plus `bytestring`,
`directory`, `data-default`, `exceptions`, `filepath`, `hslua`, `pandoc`,
`pandoc-types`, `tasty`, `tasty-golden`, `tasty-hunit`, `tasty-lua`, and
`text`. Its entry point changes into `pandoc-lua-engine/test` and runs Tasty
groups for Lua filters, Lua modules, custom writers, and custom readers.

The `pandoc-lua-engine` library closure adds HsLua module packages and Lua
marshalling support that are outside the current native PHP support rows:
`hslua-module-doclayout`, `hslua-module-path`, `hslua-module-system`,
`hslua-module-text`, `hslua-module-version`, `hslua-module-zip`, `lpeg`, and
`pandoc-lua-marshal`, plus optional `hslua-repl` when the `repl` flag is
enabled.

The pinned `cabal.project` remains part of the runner dependency closure. It
lists local packages `.`, `pandoc-lua-engine`, `pandoc-server`, and
`pandoc-cli`; enables Pandoc flags `+embed_data_files +http`; and pins Git
source-repository dependencies for:

- `doclayout` at `ef7f18308a61787244a80885d907fcd2c16604d4`
- `typst-symbols` at `6e97668c9f2ffea09f3187c34b7641038370fd21`
- `typst-hs` at `19e835d40663a92df5bed4e8a0fca5465cacdd6b`
- `texmath` at `0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a`
- `citeproc` at `1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd`

A future runner plan therefore must resolve Hackage packages plus those exact
Git pins. The wasm-only conditional source-repository pins in `cabal.project`
are not part of the normal native runner activation path unless a future slice
explicitly targets wasm runner parity.

## Current Activation Decision

No safe local upstream-runner build step is available in this worktree. The
toolchain binaries are present, but the hydrated Pandoc checkout and its
`cabal.project`, `pandoc.cabal`, and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files are absent. Running Cabal
from this isolated lane would require hydrating or fetching the broad upstream
checkout plus resolving and building the Haskell dependency graph before a
non-mutating solver/build plan could be recorded.

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
surfaces. It claims no additional native mapping or upstream-runner parity.

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
  - `20 test files, 7,302 assertions, 0 failures`
  - `rg -c '^PASS' /tmp/pandoc-runner-deps-050552-tests-after.log` reported
    `636`.
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
