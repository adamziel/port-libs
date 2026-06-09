# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T081903Z`.

Accepted base: `68ed309bdf764e315275d094e1af7ce91bb3c695`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal build, Haskell test binary, Word,
LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF engine, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 743 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF checks mapped, and 302 focused PHP PASS lines with
  0 failures.
- The current accepted source includes native ZIP/OPC/YAML/doctemplate/CSL,
  minimal DOCX body/core-property/style/numbering/table-span parsing, bounded
  ODT package/content/style/meta parsing, and bounded math/TeX handoff.
- Local cache searches found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` under
  `/home/claude/port-libs/.upstream-cache`.
- A worktree-local search also found no Pandoc Cabal package/project files.
- `ghc` is available as version 9.10.3 and `cabal` is available as
  version 3.12.1.0. `stack` is not on `PATH`.

## Primary Source Audit

Because the hydrated Pandoc checkout is absent locally, this audit used only
the pinned upstream source files at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` for static runner-dependency
classification:

- `pandoc.cabal` exists at the manifest commit and declares the main
  `test-pandoc` suite. Its direct test dependencies include the Pandoc package
  plus HUnit/golden/QuickCheck/Tasty runner packages and fixture/runtime
  helpers such as `directory`, `process`, `temporary`, `time`, XML, and ZIP
  support.
- `pandoc-lua-engine/pandoc-lua-engine.cabal` exists at the manifest commit
  and declares `test-pandoc-lua-engine`. Its direct test dependencies include
  `pandoc-lua-engine`, `pandoc`, `pandoc-types`, HsLua, Tasty/HUnit/golden/Lua
  runner packages, and file/text support packages.
- `cabal.project` includes the local packages `.`, `pandoc-lua-engine`,
  `pandoc-server`, and `pandoc-cli`; pins source-repository packages for
  `doclayout`, `typst-symbols`, `typst-hs`, `texmath`, and `citeproc`; and
  includes additional WASM-oriented source-repository packages and patch
  commands. That makes a source-hydrated project checkout, not a loose single
  Cabal file, the correct upstream-runner dependency unit.
- `test/test-pandoc.hs` wires the executable through Tasty groups for command,
  shared, media bag, XML, writer, and reader suites and also includes an
  `--emulate` branch for Pandoc command behavior. Building the test executable
  is therefore the prerequisite for command/golden parity, not just a PHP
  support-library change.

Primary source URLs inspected:

- https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/pandoc.cabal
- https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/pandoc-lua-engine/pandoc-lua-engine.cabal
- https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/cabal.project
- https://raw.githubusercontent.com/jgm/pandoc/0640c4c9859aa5a3ede082c190fcd5883c24ac83/test/test-pandoc.hs

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component. Full upstream runner parity still
needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream package/project files,
the `test-pandoc` and `test-pandoc-lua-engine` Tasty executables, and a stable
Cabal dependency plan/build for command, reader/writer, HUnit,
QuickCheck/golden, XML/media, and Lua-engine coverage.

This audit did not run `cabal update`, `cabal build`, `cabal test`, or any
dry-run that could download or mutate a Cabal store. A future runner slice
should first hydrate the exact checkout and record a non-mutating project plan
for targets equivalent to:

```text
pandoc:test:test-pandoc
pandoc-lua-engine:test:test-pandoc-lua-engine
```

Only after that plan is reproducible should a separate bounded runner slice
attempt a focused Haskell test executable build or execution.

## Dependency-Backlog Decision

No new native support component is activated by this audit. The pinned Cabal
and project files confirm that the existing bounded support rows remain the
right conversion-coverage path:

- `shared-zip-package-core` and `archive-compression-streams`
- `opc-xml-relationships-core`
- `pandoc-doctemplates-core`
- `pandoc-yaml-metadata-core`
- `citation-csl-core` and `bibtex-csl-core`
- `docx-openxml-core`
- `epub3-package-core`
- `odf-open-document-core`
- `legacy-doc-cfb-core`
- `math-tex-conversion-core`
- `syntax-highlighting-core`
- `charset-unicode-width-core`
- `table-geometry-core`
- `pandoc-pdf-engine-handoff-core`

The currently accepted native support rows improve conversion readiness, but
they do not remove the Haskell upstream-runner build gate.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 7 test files, 2,857
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
