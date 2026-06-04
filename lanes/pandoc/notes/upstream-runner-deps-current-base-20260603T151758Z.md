# Pandoc Upstream Runner Dependency Audit 2026-06-03

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260603T151758Z`.

Accepted base: `a4e4bde8c4bf526a0c886b7682678bc9365a94cb`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, external converter, Word, LibreOffice, `zip`/`unzip`, external template
engine, TeX/PDF engine, citeproc/BibTeX/Biber, network lookup, online service,
or new PHP support-library implementation was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under the
  handoff-candidates directory.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 707 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate checks mapped, and 288 focused PHP PASS lines with 0
  failures.
- The current accepted source includes native YAML metadata placement, ZIP/OPC
  package primitives, doctemplate pipes, CSL JSON handoff, bracketed Pandoc
  citation clusters, and ZIP entry timestamp/external-attribute preservation.
- `/home/claude/port-libs/.upstream-cache/pandoc` is absent in the current
  shared cache. The current `.upstream-cache` contains Gitoxide and libsqlite
  cache trees plus runner/cache scratch directories, but no Pandoc checkout.
- Local searches found no `pandoc.cabal`, `pandoc-lua-engine.cabal`,
  `cabal.project`, or `cabal.project.freeze` under `/home/claude/port-libs`
  outside tmux worktrees, and no such Cabal files under
  `/home/claude/port-libs/.upstream-cache`.
- Tool availability is unchanged from the prior audits: `ghc` is available as
  version 9.10.3, `cabal-install` is available as version 3.12.1.0, and
  `stack` is not on `PATH`.
- `ghc-pkg list --simple-output` only shows the compiler/core package set for
  this gate, including `Cabal`, `base`, `bytestring`, `containers`, `directory`,
  `filepath`, `mtl`, `parsec`, `process`, `text`, `time`, `transformers`, and
  `unix`. It does not show the upstream runner families needed for Pandoc test
  parity, including `tasty`, `HUnit`, `QuickCheck`, `pandoc`, `pandoc-types`,
  `skylighting`, `citeproc`, `aeson`, `yaml`, `commonmark`, or
  `commonmark-*`.
- `cabal v2-build --help` and `cabal v2-test --help` both expose
  `--dry-run`, `--only-dependencies`/`--dependencies-only`, `--offline`,
  `--project-dir`, `--project-file`, and `--builddir`. `v2-test` also exposes
  test logging and `--test-options`. Those flags are sufficient for a future
  non-mutating dependency-plan audit once the upstream package metadata exists
  locally.

## Non-Overlap

Same-base runner-deps handoffs are already queued for this lane, including
`20260603T135416Z`, `20260603T140924Z`, `20260603T142531Z`,
`20260603T144440Z`, and `20260603T150049Z`. The `20260603T140924Z` queued
patch appears to own a lane-local `UpstreamRunnerDependencyAudit` helper and
focused audit test. This handoff intentionally leaves `lane-status.json`,
production PHP, and tests untouched to avoid creating another conflicting
status/helper variant from the same accepted base. The status delta for this
worker is recorded in this note only.

## Runner Dependency Closure

The blocker remains upstream package metadata and runner/build-system
dependency closure, not a Pandoc-local PHP support component. Full upstream
runner parity still needs a hydrated Pandoc checkout at manifest commit
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

No new native support component is activated by this audit. Existing support
rows remain bounded by their own conversion gates:

- `shared-zip-package-core`
- `opc-xml-relationships-core`
- `pandoc-doctemplates-core`
- `pandoc-yaml-metadata-core`
- `citation-bibliography-csl-core`
- `docx-openxml-core`
- `legacy-doc-cfb-core`
- `epub3-package-core`
- `odf-open-document-core`
- `math-tex-conversion-core`
- `pandoc-pdf-engine-handoff-core`
- `pdf-text-dictionary-core`
- `pdf-page-render-plan-core`
- `table-geometry-core`
- `unicode-text-repair-width`
- `charset-encoding-core`
- `json-json5-document-core`
- `archive-compression-streams`

The accepted ZIP/OPC/YAML/doctemplate/CSL support rows improve native
conversion readiness, but they do not remove the Haskell upstream-runner build
gate.

## Next Activation Gate

Before claiming upstream runner dependency closure:

1. Restore or hydrate `/home/claude/port-libs/.upstream-cache/pandoc` at
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
- `php tools/run-tests.php lanes/pandoc/tests` passed for this audit handoff:
  5 test files, 2,693 assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
