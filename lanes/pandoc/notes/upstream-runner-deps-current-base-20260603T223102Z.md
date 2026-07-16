# Pandoc Upstream Runner Dependency Audit 2026-06-03

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260603T223102Z`.

Accepted base: `54bf1d956cfeeb457d8d8527d6c3da71f2375c14`
(`Integrate Pandoc DOCX OpenXML support`).

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal build, Haskell test executable, Word,
LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF engine,
bibliography manager, or online conversion service was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under the
  handoff-candidates directory.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 721 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX checks mapped, and 292 focused PHP PASS lines with 0
  failures.
- The current accepted source now includes the DOCX/OpenXML body/core-property
  slice on top of the earlier ZIP, OPC relationship, doctemplate, YAML, and
  CSL support-library slices.
- Local cache searches found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` under
  `/home/claude/port-libs/.upstream-cache`.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a Pandoc-local PHP support component. Full upstream runner parity still needs a
hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream Cabal package/project
files, and a non-mutating Cabal dependency plan for the `test-pandoc` and
`test-pandoc-lua-engine` Tasty executables from that exact checkout.

This worker could not safely re-audit the exact Cabal package dependency
closure from local source truth because the required upstream Cabal files are
absent locally. Running Cabal would require hydrating or fetching the broad
upstream checkout and then downloading/building the upstream dependency graph,
which is outside this isolated audit. The available GHC/Cabal toolchain is
therefore only a prerequisite, not evidence that the runner closure is solved.

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
- `pandoc-syntax-highlighting-core`
- `pandoc-pdf-engine-handoff-core`
- `pdf-text-dictionary-core`
- `pdf-page-render-plan-core`
- `table-geometry-core`
- `unicode-text-repair-width`
- `charset-encoding-core`
- `json-json5-document-core`
- `archive-compression-streams`

The accepted ZIP/OPC/YAML/doctemplate/CSL/DOCX support rows improve native
conversion readiness, but they do not remove the Haskell upstream-runner build
gate.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and record a
non-mutating `cabal build --dry-run` or equivalent dependency plan for
`test-pandoc` and `test-pandoc-lua-engine` from that exact checkout. Only after
that plan is local and stable should a separate runner slice attempt any
bounded Haskell test executable build or focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed:
  6 test files, 2,750 assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
