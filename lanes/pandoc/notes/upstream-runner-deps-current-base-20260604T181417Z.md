# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T181417Z`.

Accepted base: `3444a792da21cbff2a121bbe3129b57a38ba782a`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF
engine, MathJax, KaTeX, Typst, browser renderer, roff renderer, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree is at accepted base
  `3444a792da21cbff2a121bbe3129b57a38ba782a` with no pre-existing dirty
  Pandoc lane changes before this audit.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 819 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math/
  legacy-DOC-CFB/charset-Unicode checks mapped, and 362 focused PHP PASS lines
  with 0 failures.
- `/home/claude/port-libs/.upstream-cache` still has no Pandoc upstream
  directory, and a filename search found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` there.
- A repo-local filename search outside `.git` and `.tmux-team` found no Pandoc
  Cabal package or project files in the main checkout.
- `ghc` is available as version 9.10.3, `cabal-install` is available as version
  3.12.1.0, and `stack` is not on `PATH`.
- This audit read only small pinned upstream raw source files at commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83` to revalidate runner signatures:
  `pandoc.cabal` has 933 lines and SHA-256
  `352c6d1b0a07d4a5cfc16689384ffca3a3bfc52f60ab378ff3d656bcbd4a57cc`;
  `pandoc-lua-engine/pandoc-lua-engine.cabal` has 160 lines and SHA-256
  `5b9f61912bb380dd3fe5bfb8e9b2a44a5954a2e99e415602a6f5ad37e9bf22a2`;
  `cabal.project` has 120 lines and SHA-256
  `7f08713847f6a5143190de3035aca6daf4e6e9b1cfbc4429c47227e677eb464d`;
  `test/test-pandoc.hs` has 131 lines and SHA-256
  `8bd2cc938b3df7b2b7f2c007b0a079421a040f53482476799859c90c3c161c30`;
  and `pandoc-lua-engine/test/test-pandoc-lua-engine.hs` has 18 lines and
  SHA-256 `0fe95453e39422aecdba750476bc94a752df22359d8ada2ed4c5f86de7221b70`.

## Runner Dependency Closure

The 20260604T170433Z audit remains the source-truth direct dependency closure
for the two upstream Tasty runners. This current-base revalidation did not find
a local upstream checkout or Cabal project that would allow a safe non-mutating
solver/build plan.

The pinned `pandoc.cabal` still declares `test-suite test-pandoc` as an
`exitcode-stdio-1.0` executable with `main-is: test-pandoc.hs` and
`hs-source-dirs: test`. Its direct runner dependencies include the local
`pandoc` library plus `Diff`, `Glob`, `bytestring`, `containers`, `directory`,
`doctemplates`, `filepath`, `mtl`, `pandoc-types`, `process`, `tasty`,
`tasty-golden`, `tasty-hunit`, `tasty-quickcheck`, `text`, `temporary`, `time`,
`xml`, and `zip-archive`. The local `pandoc` library closure then pulls broader
format support such as JSON/YAML, CommonMark, CSL/citeproc, texmath,
skylighting, doclayout, doctemplates, ZIP, and HTTP-related packages.

The `test/test-pandoc.hs` entry point sets UTF-8 locale encoding, runs command
fixtures plus old/shared/media/XML groups, and covers writer groups including
Native, ConTeXt, LaTeX, HTML, JATS, Jira, DocBook, Markdown, Org, Plain,
AsciiDoc, Docx, RST, TEI, Markua, Muse, FB2, PowerPoint, Ms, AnnotatedTable,
and BBCode. It also covers reader groups including LaTeX, Markdown, HTML, JATS,
Jira, Org, RST, RTF, Docx, Pptx, Xlsx, ODT, Txt2Tags, EPUB, Muse, Creole, Man,
Mdoc, FB2, DokuWiki, and Pod. Its `--emulate` mode calls `convertWithOpts
noEngine`, so command-golden parity requires a compiled upstream test
executable rather than just static fixture reads.

The pinned `pandoc-lua-engine` Cabal file still declares
`test-suite test-pandoc-lua-engine` as an `exitcode-stdio-1.0` executable with
`main-is: test-pandoc-lua-engine.hs`, `hs-source-dirs: test`, and a local
`pandoc-lua-engine` dependency. The Lua-engine test entry point groups Lua
filters, Lua modules, custom writers, and custom readers. Its library closure
still adds HsLua support components that are not PHP conversion prerequisites:
`hslua-module-doclayout`, `hslua-module-path`, `hslua-module-system`,
`hslua-module-text`, `hslua-module-version`, `hslua-module-zip`, `lpeg`, and
`pandoc-lua-marshal`, with optional `hslua-repl` behind the `repl` flag.

The pinned `cabal.project` remains part of the runner closure. It lists
packages `.`, `pandoc-lua-engine`, `pandoc-server`, and `pandoc-cli`; enables
Pandoc flags `+embed_data_files +http`; and pins these Git
`source-repository-package` dependencies:

- `doclayout` at `ef7f18308a61787244a80885d907fcd2c16604d4`
- `typst-symbols` at `6e97668c9f2ffea09f3187c34b7641038370fd21`
- `typst-hs` at `19e835d40663a92df5bed4e8a0fca5465cacdd6b`
- `texmath` at `0a3fbebc5d0e21769f01b048eb63e1451ccf0e1a`
- `citeproc` at `1b684f1e06fc1093d20c1a2d474f4c3fdf2f65bd`

Those source-repository pins keep upstream runner closure outside a
Hackage-only package plan. A future runner slice needs a hydrated checkout and
an explicit cache/network policy for those exact Git dependencies before any
bounded Haskell runner build is attempted.

## Dependency-Backlog Decision

No new native PHP support component is activated by this audit. The current
blocker is upstream Haskell runner/build hydration and dependency planning, not
a missing Pandoc-local format primitive.

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

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, and `pandoc-lua-engine/pandoc-lua-engine.cabal` present.
Then record a non-mutating Cabal solver/build plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`, including the five project-pinned Git
source-repository packages and any cache/fetch policy needed for them. Only
after that plan is stable should a separate runner slice attempt any bounded
Haskell test executable build or focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `jq empty lanes/pandoc/lane-status.json && jq empty lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 11 test files, 3,329 assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
