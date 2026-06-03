# Pandoc Upstream Runner Dependency Audit 2026-06-03

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260603T082621Z`.

Accepted base: `36c59c783187352a699b8099a3a132c271310611`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc, Cabal build, Haskell test binary, external converter,
template engine, TeX/PDF engine, `zip`/`unzip`, or online service was executed as
progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under the
  handoff-candidates directory.
- The accepted lane state still records a cloned static Pandoc inventory:
  2,276 upstream test/data/benchmark artifacts inspected, 659 mapped checks,
  and 238 focused PHP passes with 0 failures.
- The local upstream cache path named by the accepted manifest,
  `/home/claude/port-libs/.upstream-cache/pandoc`, is not present in this
  isolated/current main cache. Local searches found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` under
  `/home/claude/port-libs` for this current-base audit.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.

## Runner Dependency Closure

The blocker remains runner/build-system dependency closure rather than a native
Pandoc support-library gap. Full upstream parity needs a hydrated Pandoc
checkout at the manifest commit, the `pandoc.cabal` and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files, the `test-pandoc` and
`test-pandoc-lua-engine` Tasty executables, and a Cabal dependency plan/build
for their HUnit, QuickCheck/golden, command, reader/writer, and Lua-engine
tests.

This worker could not safely re-audit the exact Cabal dependency closure from
local source truth because the upstream cache and Cabal files are absent. Running
Cabal would also require hydrating or fetching the broad upstream checkout and
building/downloading the upstream dependency graph, which is out of scope for
this isolated audit.

## Dependency-Backlog Decision

No new native support component is activated by this audit. Existing Pandoc
support rows remain governed by their own conversion gates:

- `shared-zip-package-core`
- `xml-html5-dom-core`
- `docx-openxml-core`
- `legacy-doc-cfb-core`
- `epub3-package-core`
- `odf-open-document-core`
- `pandoc-doctemplates-core`
- `pandoc-syntax-highlighting-core`
- `citation-bibliography-csl-core`
- `math-tex-conversion-core`
- `pandoc-pdf-engine-handoff-core`
- `pdf-text-dictionary-core`
- `pdf-page-render-plan-core`
- `table-geometry-core`
- `unicode-text-repair-width`
- `charset-encoding-core`
- `json-json5-document-core`
- `yaml-metadata-core`
- `archive-compression-streams`

Those rows cover rich conversion support such as packages, XML/HTML, DOCX, EPUB,
ODT, metadata, templates, citations, math, PDF handoff, tables, Unicode/charset,
and compression. They do not remove the current Haskell upstream-runner build
gate.

## Next Activation Gate

Before claiming upstream runner dependency closure, the integrator should restore
or hydrate a local Pandoc upstream checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, then record a non-mutating Cabal
dependency plan for `test-pandoc` and `test-pandoc-lua-engine` from that exact
checkout. Only after that plan is local and stable should a separate runner
slice attempt any bounded Haskell test executable build or focused upstream
runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MarkdownReaderTest.php` passed:
  1 test file, 2,315 assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
