# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T141246Z`.

Accepted base: `cf34e358e490c64eec2d089d86a82a4536a1d9b4`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal build, Haskell test binary, Word,
LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF engine, Typst,
browser renderer, roff renderer, online conversion service, or other external
converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- Current worktree `HEAD` is
  `cf34e358e490c64eec2d089d86a82a4536a1d9b4`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 777 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math/
  legacy-DOC-CFB checks mapped, and 341 focused PHP PASS lines with 0
  failures.
- The current accepted source includes bounded native support for Markdown and
  HTML reader/writer scenarios, ZIP/OPC package primitives, OPC content-types
  and relationship closure traversal, YAML metadata, doctemplate pipes and
  partials, CSL/citation handoff, minimal DOCX and ODT package reading, table
  geometry, archive compression streams, math/TeX handoff, PDF engine
  planning, and legacy DOC/CFB extraction.
- `/home/claude/port-libs/.upstream-cache` currently has no Pandoc upstream
  directory. A targeted cache search found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze`.
- A repo-local search outside `.git` and tmux worktrees found no Pandoc Cabal
  package/project files.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component. Full upstream runner parity still
needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream `pandoc.cabal` and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files, the `test-pandoc` and
`test-pandoc-lua-engine` Tasty executables, and a stable Cabal dependency
plan/build for command, reader/writer, HUnit, QuickCheck/golden, and Lua-engine
coverage.

This worker could not safely audit exact Cabal package dependency closure from
local source truth because the upstream checkout and Cabal package/project
files are absent locally. Running Cabal from this isolated lane would require
hydrating or fetching the broad upstream checkout and downloading/building the
upstream dependency graph, which is out of scope for this audit.

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

These support rows improve native conversion readiness, but they do not remove
the Haskell upstream-runner build gate.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and record a
non-mutating Cabal dependency plan for `test-pandoc` and
`test-pandoc-lua-engine` from that exact checkout. Only after that plan is
available and stable should a separate runner slice attempt any bounded
Haskell test executable build or focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 10 test files, 3,136
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
