# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T195219Z`.

Accepted base: `29ff2ff42cf82b170e1a0875645c27e8fe158329`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF
engine, MathJax, KaTeX, Typst, browser renderer, roff renderer, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree is at accepted base
  `29ff2ff42cf82b170e1a0875645c27e8fe158329` with no pre-existing dirty Pandoc
  lane changes before this audit.
- The accepted base includes the prior upstream-runner dependency audit at
  `3e0384ae3bd348c676d909f1e53e226fe39172d6` and the subsequent DOCX field-code
  hyperlink implementation at `c0b29c2f4`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 823 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math/
  legacy-DOC-CFB/charset-Unicode checks mapped, and 366 focused PHP PASS lines
  with 0 failures.
- `/home/claude/port-libs/.upstream-cache` still has no Pandoc upstream
  directory, and a filename search found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` there.
- This isolated worktree also has no Pandoc Cabal package or project files
  outside `.git` and `.tmux-team`.
- `ghc` is available as version 9.10.3, `cabal-install` is available as version
  3.12.1.0, and `stack` is not on `PATH`.
- The prior current-base dependency audit at
  `upstream-runner-deps-current-base-20260604T170433Z.md` remains the pinned
  raw-source closure record for `test-pandoc` and
  `test-pandoc-lua-engine`.

## Cabal Runner Closure

The pinned upstream runner gate remains the same as the 20260604T170433Z
source audit:

- `pandoc.cabal` declares `test-suite test-pandoc` as an
  `exitcode-stdio-1.0` Tasty executable with `main-is: test-pandoc.hs` and
  `hs-source-dirs: test`. It imports `common-executable`, which imports
  `common-options`, depends on `base >= 4.18 && < 5`, depends on the local
  `pandoc` library, and builds with `-rtsopts -with-rtsopts=-A8m -threaded`.
- The direct `test-pandoc` test dependencies include local `pandoc`,
  `Diff`, `Glob`, `bytestring`, `containers`, `directory`, `doctemplates`,
  `filepath`, `mtl`, `pandoc-types`, `process`, `tasty`, `tasty-golden`,
  `tasty-hunit`, `tasty-quickcheck`, `text`, `temporary`, `time`, `xml`, and
  `zip-archive` within the version bounds recorded in the earlier audit.
- `test/test-pandoc.hs` sets locale encoding to UTF-8, changes into `test` in
  normal mode, and runs the Tasty tree covering command fixtures, old tests,
  shared helpers, media bag, XML, writer groups, and reader groups. With
  `--emulate`, it acts as a Pandoc command runner by calling
  `convertWithOpts noEngine`, so command-golden parity needs the compiled test
  executable.
- `pandoc-lua-engine/pandoc-lua-engine.cabal` declares
  `test-suite test-pandoc-lua-engine` as an `exitcode-stdio-1.0` Tasty
  executable with `main-is: test-pandoc-lua-engine.hs` and
  `hs-source-dirs: test`.
- The direct Lua-engine test dependencies include local `pandoc-lua-engine`,
  `bytestring`, `directory`, `data-default`, `exceptions`, `filepath`,
  `hslua`, `pandoc`, `pandoc-types`, `tasty`, `tasty-golden`, `tasty-hunit`,
  `tasty-lua`, and `text`.
- The Lua-engine library closure also pulls HsLua module packages that are not
  native Pandoc PHP support components: `hslua-module-doclayout`,
  `hslua-module-path`, `hslua-module-system`, `hslua-module-text`,
  `hslua-module-version`, `hslua-module-zip`, `lpeg`, and
  `pandoc-lua-marshal`, plus optional `hslua-repl`.
- `cabal.project` is part of the runner dependency closure. It lists packages
  `.`, `pandoc-lua-engine`, `pandoc-server`, and `pandoc-cli`; enables Pandoc
  flags `+embed_data_files +http`; and pins Git source-repository packages
  `doclayout`, `typst-symbols`, `typst-hs`, `texmath`, and `citeproc` at the
  exact commits recorded in the 20260604T170433Z audit.

## Current Activation Decision

No safe local upstream-runner build step is available in this worktree. The
toolchain binaries are present, but the hydrated Pandoc checkout and its
`cabal.project`, `pandoc.cabal`, and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files are absent. Running Cabal
from this isolated lane would therefore require hydrating or fetching upstream
source and resolving/building the broad Haskell dependency graph before a
non-mutating solver plan could be recorded.

This keeps the full upstream runner gate open. It does not block the accepted
native PHP conversion slices for Markdown/HTML, ZIP/OPC, YAML, CSL, DOCX/ODT,
table geometry, math/TeX, PDF handoff planning, archive compression streams,
charset/Unicode support, doctemplates, or legacy DOC/CFB.

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

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present. Then
record a non-mutating Cabal solver/build plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`, including how the five project-pinned Git
source-repository packages are resolved. Only after that plan is stable should
a separate runner slice attempt any bounded Haskell test executable build or
focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 11 test files, 3,398
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
