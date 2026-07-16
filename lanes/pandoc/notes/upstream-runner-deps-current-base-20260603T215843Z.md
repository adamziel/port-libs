# Pandoc Upstream Runner Dependency Audit 2026-06-03

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260603T215843Z`.

Accepted base: `ee5bab2f1ee2c0907fe52d29b7278104c9b95fba`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal build, Haskell test binary, external
converter, Word, LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF
engine, online service, or new support-library implementation was executed as
progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 707 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate checks mapped, and 288 focused PHP PASS lines with 0
  failures.
- The current accepted source includes the 09:30 UTC Pandoc batch: bracketed
  citation cluster parsing/rendering, bounded CSL JSON bibliography handoff,
  ZIP entry modification timestamp/external-attribute preservation, generated
  ZIP package metadata options, and local-header timestamp integrity.
- Local searches found no `pandoc.cabal`, `pandoc-lua-engine.cabal`,
  `cabal.project`, or `cabal.project.freeze` under `/home/claude/port-libs`
  outside tmux worktrees, and no Pandoc upstream cache directory or Cabal files
  under `/home/claude/port-libs/.upstream-cache`.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component. Full upstream runner parity still
needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream `pandoc.cabal` and
`pandoc-lua-engine/pandoc-lua-engine.cabal` package files, the `test-pandoc`
and `test-pandoc-lua-engine` Tasty executables, and a stable Cabal dependency
plan/build for their command, reader/writer, HUnit, QuickCheck/golden, and
Lua-engine coverage.

This worker could not safely re-audit exact Cabal package dependency closure
from local source truth because the upstream package/project files are absent
locally. Running Cabal would require hydrating or fetching the broad upstream
checkout and building/downloading the upstream dependency graph, which is out of
scope for this isolated audit.

## Dependency-Backlog Decision

No new native support component is activated by this audit. Existing support
rows remain bounded by their own conversion gates:

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

The accepted ZIP/OPC/YAML/doctemplate/CSL support rows improve native
conversion readiness, but they do not remove the Haskell upstream-runner build
gate.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and record a
non-mutating Cabal dependency plan for `test-pandoc` and
`test-pandoc-lua-engine` from that exact checkout. Only after that plan is
available and stable should a separate runner slice attempt any bounded Haskell
test executable build or focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 5 test files, 2,693
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
