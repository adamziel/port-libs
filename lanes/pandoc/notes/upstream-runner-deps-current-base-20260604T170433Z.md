# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T170433Z`.

Accepted base: `a7eb53e31a126ba8e38fd7f456f65421a12e11ac`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF
engine, MathJax, KaTeX, Typst, browser renderer, roff renderer, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree is at accepted base
  `a7eb53e31a126ba8e38fd7f456f65421a12e11ac` with no pre-existing dirty
  Pandoc lane changes before this audit.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 816 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math/
  legacy-DOC-CFB/charset-Unicode checks mapped, and 359 focused PHP PASS lines
  with 0 failures.
- `/home/claude/port-libs/.upstream-cache` still has no Pandoc upstream
  directory, and a filename search found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` there.
- A repo-local filename search outside `.git` and `.tmux-team` also found no
  Pandoc Cabal package or project files in the main checkout.
- `ghc` is available as version 9.10.3, `cabal-install` is available as version
  3.12.1.0, and `stack` is not on `PATH`.
- Because the local Pandoc checkout is absent, this audit read only the pinned
  upstream raw source files at commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`:
  `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
  `cabal.project`, `test/test-pandoc.hs`, and
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`.

## Cabal Runner Closure

`pandoc.cabal` declares `test-suite test-pandoc` as an `exitcode-stdio-1.0`
Tasty executable with `main-is: test-pandoc.hs` and `hs-source-dirs: test`. It
imports `common-executable`, which imports `common-options`, depends on
`base >= 4.18 && < 5`, depends on the local `pandoc` library, and builds with
`-rtsopts -with-rtsopts=-A8m -threaded`.

The direct `test-pandoc` dependency set from the pinned Cabal file is:

- local `pandoc`
- `Diff >= 0.2 && < 1.1`
- `Glob >= 0.7 && < 0.11`
- `bytestring >= 0.9 && < 0.13`
- `containers >= 0.4.2.1 && < 0.9`
- `directory >= 1.2.3 && < 1.4`
- `doctemplates >= 0.11 && < 0.12`
- `filepath >= 1.1 && < 1.6`
- `mtl >= 2.2 && < 2.4`
- `pandoc-types >= 1.23.1 && < 1.24`
- `process >= 1.2.3 && < 1.7`
- `tasty >= 0.11 && < 1.6`
- `tasty-golden >= 2.3 && < 2.4`
- `tasty-hunit >= 0.9 && < 0.11`
- `tasty-quickcheck >= 0.8 && < 0.12`
- `text >= 1.1.1.0 && < 2.2`
- `temporary >= 1.1 && < 1.4`
- `time >= 1.5 && < 1.16`
- `xml >= 1.3.12 && < 1.4`
- `zip-archive >= 0.4.3 && < 0.5`

The `test-pandoc.hs` entry point sets locale encoding to UTF-8. In normal mode
it changes into the `test` directory and runs the Tasty tree covering command
fixtures, old tests, shared helpers, media bag, XML, writer groups, and reader
groups. With `--emulate`, the same executable acts as a Pandoc command runner
by calling `convertWithOpts noEngine`, so command-golden parity requires the
compiled test executable and not just library modules.

`pandoc-lua-engine/pandoc-lua-engine.cabal` declares
`test-suite test-pandoc-lua-engine` as an `exitcode-stdio-1.0` Tasty executable
with `main-is: test-pandoc-lua-engine.hs` and `hs-source-dirs: test`. Its
common options depend on `base >= 4.12 && < 5`. The direct Lua-engine test
dependency set is:

- local `pandoc-lua-engine`
- `bytestring`
- `directory`
- `data-default`
- `exceptions >= 0.8 && < 0.11`
- `filepath`
- `hslua >= 2.5 && < 2.6`
- `pandoc`
- `pandoc-types >= 1.22 && < 1.24`
- `tasty`
- `tasty-golden`
- `tasty-hunit`
- `tasty-lua >= 1.1 && < 1.2`
- `text >= 1.1.1 && < 2.2`

The `pandoc-lua-engine` library closure adds HsLua module dependencies that are
not represented by any current native PHP support component:
`hslua-module-doclayout`, `hslua-module-path`, `hslua-module-system`,
`hslua-module-text`, `hslua-module-version`, `hslua-module-zip`, `lpeg`, and
`pandoc-lua-marshal`, plus its own optional `repl` flag dependency on
`hslua-repl`. The test entry point changes into `pandoc-lua-engine/test` and
runs four Tasty groups: Lua filters, Lua modules, custom writers, and custom
readers.

`cabal.project` is part of the runner dependency closure. It lists packages
`.`, `pandoc-lua-engine`, `pandoc-server`, and `pandoc-cli`; sets constraints
for `skylighting-format-blaze-html`, `skylighting-format-context`,
`auto-update`, and `crypton`; enables `pandoc` flags `+embed_data_files +http`;
and pins these Git source-repository packages:

- `doclayout` at `ef7f18308a61787244a80885d907fcd2c16604d4`
- `typst-symbols` at `6e97668c9f2ffea09f3187c34b7641038370fd21`
- `typst-hs` at `19e835d40663a92df5bed4e8a0fca5465cacdd6b`
- `texmath` at `0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a`
- `citeproc` at `1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd`

Those pinned source repositories mean a future runner build is not closed by
Hackage packages alone. It needs a hydrated checkout with `cabal.project` and
network/cache policy for those exact Git dependencies.

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
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present.
Then record a non-mutating Cabal solver/build plan for
`test:test-pandoc` and `test:test-pandoc-lua-engine`, including how the five
project-pinned Git source-repository packages are resolved. Only after that
plan is stable should a separate runner slice attempt any bounded Haskell test
executable build or focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 11 test files, 3,277
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
