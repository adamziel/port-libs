# Pandoc Upstream Runner Dependency Audit 2026-06-03

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260603T084924Z`.

Accepted base: `f0bd4183a2ffe1c741d3688a1bfed43e7facac09`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc, Cabal build, Haskell test binary, external
converter, template engine, TeX/PDF engine, `zip`/`unzip`, online service, or
new support-library implementation was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under the
  handoff-candidates directory.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 670 focused Markdown/HTML/WordPress/package checks
  mapped, and 265 focused PHP PASS lines with 0 failures.
- Recent accepted native support-library slices cover ZIP package reads, OPC
  content-types and relationships XML, Pandoc doctemplate rendering, and
  leading YAML metadata parsing.
- Local searches found no `pandoc.cabal`, `pandoc-lua-engine.cabal`,
  `cabal.project`, or `cabal.project.freeze` under `/home/claude/port-libs`
  outside the tmux worktree area, and no such Cabal files under
  `/home/claude/port-libs/.upstream-cache`.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component. Full upstream parity still needs a
hydrated Pandoc checkout at the manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream Cabal package files,
the `test-pandoc` and `test-pandoc-lua-engine` Tasty executables, and a stable
Cabal dependency plan/build for their HUnit, QuickCheck/golden, command,
reader/writer, and Lua-engine tests.

This worker could not safely re-audit exact Cabal package dependency closure
from local upstream source truth because the Cabal files are absent locally.
Running Cabal would require hydrating or fetching the broad upstream checkout
and building/downloading the upstream dependency graph, which is outside this
isolated audit.

## Dependency-Backlog Decision

No new native support component is activated by this audit. Existing support
rows remain bounded by their own conversion gates:

- `shared-zip-package-core`
- `opc-xml-relationships-core`
- `pandoc-doctemplates-core`
- `pandoc-yaml-metadata-core`
- `docx-openxml-core`
- `legacy-doc-cfb-core`
- `epub3-package-core`
- `odf-open-document-core`
- `citation-bibliography-csl-core`
- `math-tex-conversion-core`
- `pandoc-pdf-engine-handoff-core`
- `pdf-text-dictionary-core`
- `pdf-page-render-plan-core`
- `table-geometry-core`
- `unicode-text-repair-width`
- `charset-encoding-core`
- `json-json5-document-core`
- `archive-compression-streams`

The accepted ZIP/OPC/YAML/doctemplate support rows improve native conversion
readiness, but they do not remove the Haskell upstream-runner build gate.

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
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed:
  1 test file, 2,346 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed:
  4 test files, 2,476 assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
