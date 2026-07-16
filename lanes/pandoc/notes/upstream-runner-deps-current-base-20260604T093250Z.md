# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T093250Z`.

Accepted base: `1897321874cf88908aabd37434234bcbcba16d7e`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal build, Haskell test binary, Word,
LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF engine, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 750 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX/ODF/table-geometry/archive-compression/math checks
  mapped, and 310 focused PHP PASS lines with 0 failures.
- The current accepted source includes native ZIP/OPC/YAML/doctemplate/CSL,
  minimal DOCX and ODT package parsing, table geometry, and bounded Math/TeX
  conversion support.
- The local upstream cache currently has no
  `/home/claude/port-libs/.upstream-cache/pandoc` checkout. The visible cache
  roots are for other lanes such as gitoxide, libsqlite, lightningcss, and
  SQLite runner artifacts.
- Searches of the local upstream cache and this isolated worktree found no
  `pandoc.cabal`, `pandoc-lua-engine.cabal`, `cabal.project`,
  `cabal.project.freeze`, or `stack.yaml` files for Pandoc.
- `ghc` is available as version 9.10.3 and `cabal` is available as version
  3.12.1.0. `stack` is not on `PATH`.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component for this audit. Full upstream
runner parity still needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream `pandoc.cabal` and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files, the `test-pandoc` and
`test-pandoc-lua-engine` Tasty executables, and a stable Cabal dependency
plan/build for their command, reader/writer, HUnit, QuickCheck/golden, and
Lua-engine coverage.

This worker could not safely re-audit exact Cabal package dependency closure
from local source truth because the upstream checkout and Cabal package/project
files are absent locally on the accepted base. Running Cabal would require
hydrating or fetching the broad upstream checkout and building/downloading the
upstream dependency graph, which is out of scope for this isolated audit.

## Dependency-Backlog Decision

No new native support component is activated by this audit. Existing bounded
support rows remain the correct dependency closure path for real conversion
coverage:

- `shared-zip-package-core`
- `opc-xml-relationships-core`
- `pandoc-doctemplates-core`
- `pandoc-yaml-metadata-core`
- `citation-csl-core`
- `bibtex-csl-core`
- `docx-openxml-core`
- `epub3-package-core`
- `odf-open-document-core`
- `legacy-doc-cfb-core`
- `math-tex-conversion-core`
- `syntax-highlighting-core`
- `charset-unicode-width-core`
- `table-geometry-core`
- `archive-compression-streams`
- `pandoc-pdf-engine-handoff-core`

The accepted native support rows improve conversion readiness, but they do not
remove the Haskell upstream-runner build gate.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and record a
non-mutating dependency plan for `test-pandoc` and `test-pandoc-lua-engine`
from that exact checkout. Only after that plan is available and stable should a
separate runner slice attempt any bounded Haskell test executable build or
focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'` passed.
- `php tools/run-tests.php lanes/pandoc/tests/MathTexConverterTest.php` passed:
  1 test file, 16 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests/ZipPackageTest.php` remains blocked
  by the pre-existing missing `PortLibs\Pandoc\GzipStream` class: 1 test file,
  106 assertions, 3 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
