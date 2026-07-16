# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T163112Z`.

Accepted base: `936e01b75da46937a815da1572560a12a8f9c02b`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF
engine, MathJax, KaTeX, Typst, browser renderer, roff renderer, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree is at accepted base
  `936e01b75da46937a815da1572560a12a8f9c02b` with no pre-existing dirty lane
  changes before this note/status update.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 802 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math/
  legacy-DOC-CFB/charset-Unicode checks mapped, and 356 focused PHP PASS lines
  with 0 failures.
- The current accepted source includes native Markdown and HTML reader/writer
  evidence, charset decoding/Unicode repair/display-width helpers, YAML
  metadata, ZIP/OPC package primitives, OPC relationship target preflight and
  reachable closure traversal, doctemplate pipes/partials, CSL/citation
  handoff, minimal DOCX and ODT package reading, table geometry, archive
  compression streams, math/TeX matrix/aligned handoff, PDF engine planning,
  and legacy DOC/CFB extraction.
- Cache search output was empty for Pandoc upstream/cache metadata:
  `find /home/claude/port-libs/.upstream-cache -maxdepth 4 \( -iname '*pandoc*'
  -o -name 'pandoc.cabal' -o -name 'pandoc-lua-engine.cabal' -o -name
  'cabal.project' -o -name 'cabal.project.freeze' \) -print`.
- Main-repo search output was empty for Pandoc Cabal package/project files
  outside `.git` and `.tmux-team`.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.
- `ghc-pkg list --simple-output` currently shows the compiler/core package set
  only. A targeted package probe found no installed `tasty`, `HUnit`,
  `QuickCheck`, `pandoc`, `pandoc-types`, `skylighting`, `citeproc`, `aeson`,
  `yaml`, or `commonmark*` packages.
- `cabal v2-build --help` and `cabal v2-test --help` expose `--dry-run`,
  `--only-dependencies`/`--dependencies-only`, `--project-file`, and
  `--builddir`. `v2-test` also exposes `--test-show-details` and
  `--test-options`. Those flags are sufficient for a future non-mutating
  dependency-plan audit after the exact upstream package metadata exists
  locally.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component. Full upstream runner parity still
needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream `pandoc.cabal` and
`pandoc-lua-engine/pandoc-lua-engine.cabal` package files, any upstream
`cabal.project` or freeze file present at that commit, and a stable dependency
plan/build for the `test-pandoc` and `test-pandoc-lua-engine` Tasty
executables.

This worker could not safely re-audit exact Cabal package dependency closure
from local source truth because the upstream checkout and Cabal package/project
files are absent locally. Running Cabal from this isolated lane would require
hydrating or fetching the broad upstream checkout plus downloading/building the
Pandoc dependency graph. That remains outside this audit slice.

## Dependency-Backlog Decision

No new native support component is activated by this audit. Existing bounded
support rows remain the correct dependency closure path for real conversion
coverage:

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

The accepted native support rows improve conversion readiness, but they do not
remove the Haskell upstream-runner build gate.

## Next Activation Gate

Before claiming upstream runner dependency closure:

1. Hydrate `/home/claude/port-libs/.upstream-cache/pandoc` at
   `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
2. Record the exact upstream package metadata used for planning:
   `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, and any
   upstream `cabal.project` or freeze file present at that commit.
3. Run a non-mutating Cabal dependency plan from the hydrated checkout before
   any build or test execution, using explicit `--dry-run`,
   `--only-dependencies`, `--project-file` when applicable, and a lane-local
   `--builddir`.
4. Record whether the plan resolves `test-pandoc` and
   `test-pandoc-lua-engine`, including runner packages such as `tasty`,
   `HUnit`, `QuickCheck`, `pandoc`, `pandoc-types`, `skylighting`, `citeproc`,
   `aeson`, `yaml`, and `commonmark*`.
5. Only after the checkout and dependency plan are local, stable, and recorded
   should a separate runner slice attempt any bounded Haskell test executable
   build or focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 11 test files, 3,260
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
