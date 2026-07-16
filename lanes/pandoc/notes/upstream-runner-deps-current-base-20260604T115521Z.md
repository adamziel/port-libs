# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T115521Z`.

Accepted base: `85fd4cc95787bd0f1b632db7ee5c4e186dc6e5f4`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF
engine, browser renderer, roff/Typst renderer, online conversion service, or
new PHP support-library implementation was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 754 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math/PDF handoff
  checks mapped, and 323 focused PHP PASS lines with 0 failures.
- The current accepted source includes native Markdown/HTML reader and writer
  coverage, YAML metadata placement, ZIP/OPC package primitives, doctemplate
  pipes, CSL JSON citation handoff, DOCX/ODT package parsing, table geometry,
  archive compression stream helpers, math/TeX handoff, and bounded PDF engine
  source planning/fake-runner diagnostics.
- Local cache searches found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` under
  `/home/claude/port-libs/.upstream-cache`.
- A repo-local search outside the `.git` directory and tmux-team scratch tree
  found no Pandoc Cabal package/project files in this isolated worktree.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.
- `ghc-pkg list --simple-output` shows only the compiler/core package set for
  this gate, including `Cabal`, `base`, `bytestring`, `containers`,
  `directory`, `filepath`, `mtl`, `parsec`, `process`, `text`, `time`,
  `transformers`, and `unix`. It does not show the upstream runner families
  needed for Pandoc test parity, including `tasty`, `HUnit`, `QuickCheck`,
  `pandoc`, `pandoc-types`, `skylighting`, `citeproc`, `aeson`, `yaml`,
  `commonmark`, or `commonmark-*`.
- `cabal v2-build --help` and `cabal v2-test --help` both expose `--dry-run`,
  `--only-dependencies`/`--dependencies-only`, `--offline`, `--project-dir`,
  `--project-file`, and `--builddir`; `v2-test` also exposes test logging and
  `--test-options`. Those flags are enough for a future non-mutating dependency
  plan after the upstream package metadata exists locally.

## Runner Dependency Closure

The blocker remains upstream package metadata and Haskell runner dependency
closure, not a missing Pandoc-local PHP support component. Full upstream runner
parity still needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream `pandoc.cabal` and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files, any upstream project/freeze
files present at that commit, and then a stable dependency plan for the
`test-pandoc` and `test-pandoc-lua-engine` Tasty executables.

This worker could not safely re-audit exact Cabal package dependency closure
from local source truth because the Pandoc upstream checkout and Cabal
package/project files are absent locally. Running Cabal against the lane now
would either fail before source-truth package resolution or require hydrating
or fetching the broad upstream checkout and downloading/building dependencies,
which is outside this isolated audit.

## Dependency-Backlog Decision

No new native support component is activated by this audit. Existing bounded
support rows remain the correct conversion-readiness path:

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

The accepted native support rows improve real conversion coverage, but they do
not remove the Haskell upstream-runner build gate.

## Next Activation Gate

Before claiming upstream runner dependency closure:

1. Hydrate `/home/claude/port-libs/.upstream-cache/pandoc` at
   `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.
2. Record the exact package metadata files used for dependency planning:
   `pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, and any
   upstream `cabal.project` or freeze file present at that commit.
3. Run a non-mutating Cabal plan from the hydrated checkout before any build or
   test execution. The planned command should use a lane-local build directory
   and conservative flags such as `--dry-run`, `--only-dependencies`,
   `--offline` if the index/store is already available, and explicit
   `--project-dir`/`--project-file` values if upstream provides a project file.
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
- `php tools/run-tests.php lanes/pandoc/tests` passed: 9 test files, 3,047
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
