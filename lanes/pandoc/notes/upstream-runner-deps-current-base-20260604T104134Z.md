# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T104134Z`.

Accepted base: `ceef47806c3d0e479408d9ba3cd04205f40c9bee`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal build, Haskell test binary, Word,
LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF engine, online
conversion service, or external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 752 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math checks
  mapped, and 319 focused PHP PASS lines with 0 failures.
- The current accepted source includes native ZIP/OPC/YAML/doctemplate/CSL,
  DOCX comments/endnotes, ODT, math/TeX, PDF engine handoff, table geometry,
  and bounded gzip stream support.
- Local cache searches found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` under
  `/home/claude/port-libs/.upstream-cache`.
- A repo-local search in this isolated worktree also found no Pandoc Cabal
  package/project files.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.

## Bounded Upstream Package Metadata

To avoid hydrating or building the full Haskell workspace, this audit only read
the raw upstream package metadata at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

Direct `pandoc.cabal` `test-suite test-pandoc` build dependencies at that
commit are:

- `pandoc`
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

Direct `pandoc-lua-engine/pandoc-lua-engine.cabal`
`test-suite test-pandoc-lua-engine` build dependencies at that commit are:

- `pandoc-lua-engine`
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

The upstream `cabal.project` at that commit includes the local packages `.`,
`pandoc-lua-engine`, `pandoc-server`, and `pandoc-cli`; constraints for
`skylighting-format-blaze-html`, `skylighting-format-context`, `auto-update`,
and `crypton`; and source-repository-package pins for `doclayout`,
`typst-symbols`, `typst-hs`, `texmath`, and `citeproc`.

No `cabal.project.freeze` exists at the pinned commit. Without a hydrated
checkout plus a recorded Cabal solver plan, this audit still cannot claim exact
runner dependency closure.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component. Full upstream runner parity still
needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the package files above, the
`test-pandoc` and `test-pandoc-lua-engine` Tasty executables, the project-local
source-repository-package pins, and a stable Cabal solver/build plan for their
command, reader/writer, HUnit, QuickCheck/golden, and Lua-engine coverage.

Running Cabal would require hydrating or fetching the broad upstream checkout
and downloading/building the upstream dependency graph, which is out of scope
for this isolated audit.

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

Before claiming upstream runner dependency closure, hydrate a local Pandoc
upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83`, preserve the
project-local source-repository-package pins, and record a non-mutating Cabal
dependency plan for `test-pandoc` and `test-pandoc-lua-engine` from that exact
checkout. Only after that plan is available and stable should a separate runner
slice attempt any bounded Haskell test executable build or focused upstream
runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 9 test files, 3,015
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
