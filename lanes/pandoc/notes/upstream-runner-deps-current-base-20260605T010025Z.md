# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T010025Z`.

Accepted base: `30e2b804932d6dd9523e012bfcef495e9361dc0d`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`, external template
engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff renderer,
media player, online conversion service, online sanitizer, or other external
converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree is at accepted base
  `30e2b804932d6dd9523e012bfcef495e9361dc0d` with no pre-existing dirty
  Pandoc lane changes before this audit.
- Current Pandoc lane counters are 2,276 upstream test/data/benchmark artifacts
  inspected, 958 mapped native PHP checks, and 485 focused PHP PASS lines with
  0 failures.
- `/home/claude/port-libs/.upstream-cache/pandoc` and
  `/home/claude/port-libs/.upstream-cache/pandoc-build-port-pandoc` are absent.
  A filename search under `/home/claude/port-libs/.upstream-cache` found no
  `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`, or
  `cabal.project.freeze`.
- This isolated worktree also has no Pandoc Cabal package or project files
  outside `.git` and `.tmux-team`.
- `ghc` is available as version 9.10.3, `cabal-install` is available as version
  3.12.1.0, and `stack` is not on `PATH`.
- The dependency audit at
  `upstream-runner-deps-current-base-20260604T170433Z.md` remains the pinned
  raw-source closure source for `test-pandoc` and `test-pandoc-lua-engine` at
  upstream commit `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Runner Dependency Closure

The pinned `test-pandoc` runner is a Haskell `exitcode-stdio-1.0` Tasty
executable. Its direct Cabal closure includes the local `pandoc` library plus
`Diff`, `Glob`, `bytestring`, `containers`, `directory`, `doctemplates`,
`filepath`, `mtl`, `pandoc-types`, `process`, `tasty`, `tasty-golden`,
`tasty-hunit`, `tasty-quickcheck`, `text`, `temporary`, `time`, `xml`, and
`zip-archive`. Its `--emulate` path acts as the Pandoc command runner, so
command-golden parity requires the compiled test executable rather than static
source reads alone.

The pinned `test-pandoc-lua-engine` runner is also a Tasty executable. Its
direct test closure includes the local `pandoc-lua-engine` library plus
`bytestring`, `directory`, `data-default`, `exceptions`, `filepath`, `hslua`,
`pandoc`, `pandoc-types`, `tasty`, `tasty-golden`, `tasty-hunit`, `tasty-lua`,
and `text`. The Lua engine library closure adds HsLua module packages and Lua
marshalling support that are outside the current native PHP support rows.

The pinned `cabal.project` is part of the runner dependency closure. It pulls
local packages `.`, `pandoc-lua-engine`, `pandoc-server`, and `pandoc-cli`,
enables Pandoc data/http flags, and pins Git source-repository dependencies for
`doclayout`, `typst-symbols`, `typst-hs`, `texmath`, and `citeproc`. A future
runner plan therefore must resolve Hackage packages plus those exact Git pins.

## Current Activation Decision

No safe local upstream-runner build step is available in this worktree. The
toolchain binaries are present, but the hydrated Pandoc checkout and its
`cabal.project`, `pandoc.cabal`, and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files are absent. Running Cabal
from this isolated lane would require hydrating/fetching the broad upstream
checkout plus resolving/building the Haskell dependency graph before a
non-mutating solver plan could be recorded.

This keeps the full upstream runner gate open. It does not block accepted
native PHP conversion slices for Markdown/HTML, XML/HTML5 DOM, ZIP/OPC, YAML,
CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX, PDF handoff planning,
archive compression streams, charset/Unicode support, doctemplates, or legacy
DOC/CFB.

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
surfaces. It claims no additional native mapping or PHP PASS growth.

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
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 19 test files, 4,962
  assertions, 0 failures.
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
