# Pandoc Upstream Runner Dependency Audit 2026-06-03

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260603T233842Z`.

Accepted base: `9f8ae4fb71ff5c28527f923a11d9eebb6d57eab4`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal build, Haskell test binary, Word,
LibreOffice, `zip`/`unzip`, external template engine, TeX/PDF engine, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 734 focused Markdown/HTML/WordPress/package/CSL/YAML/
  OPC/doctemplate/DOCX checks mapped, and 298 focused PHP PASS lines with 0
  failures.
- The current accepted source includes native ZIP/OPC/YAML/doctemplate/CSL
  support, minimal DOCX body/core-property/style/numbering parsing into the
  shared AST, and bounded math/TeX conversion handoff for LaTeX and MathML
  output without TeX engine execution.
- No hydrated Pandoc upstream checkout was present under
  `/home/claude/port-libs/.upstream-cache` for this current-base audit.
- Local cache searches found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze` under
  `/home/claude/port-libs/.upstream-cache`.
- A repo-local search outside tmux worktrees and `.git` also found no Pandoc
  Cabal package/project files.
- `ghc` is available as version 9.10.3 and `cabal` is available as
  version 3.12.1.0. `stack` is not on `PATH`.

## Runner Dependency Closure

The blocker remains upstream runner/build-system dependency closure rather than
a missing Pandoc-local PHP support component. Full upstream runner parity still
needs a hydrated Pandoc checkout at manifest commit
`0640c4c9859aa5a3ede082c190fcd5883c24ac83`, the upstream `pandoc.cabal` and
`pandoc-lua-engine/pandoc-lua-engine.cabal` files, the `test-pandoc` and
`test-pandoc-lua-engine` Tasty executables, and a stable Cabal dependency
plan/build for their command, reader/writer, HUnit, QuickCheck/golden, and
Lua-engine coverage.

This worker could not safely re-audit exact Cabal package dependency closure
from local source truth because the upstream checkout and Cabal package/project
files are absent locally. Running Cabal would require hydrating or fetching the
broad upstream checkout and building/downloading the upstream dependency graph,
which is out of scope for this isolated audit.

## Dependency-Backlog Decision

No new native support component is activated by this audit. Existing bounded
support rows remain the correct dependency closure path for real conversion
coverage:

- `shared-zip-package-core` (`candidate`)
- `xml-html5-dom-core` (`candidate`)
- `docx-openxml-core` (`candidate`)
- `legacy-doc-cfb-core` (`candidate`)
- `epub3-package-core` (`candidate`)
- `odf-open-document-core` (`deferred`)
- `pandoc-doctemplates-core` (`candidate`)
- `pandoc-syntax-highlighting-core` (`candidate`)
- `citation-bibliography-csl-core` (`deferred`)
- `math-tex-conversion-core` (`deferred`)
- `pandoc-pdf-engine-handoff-core` (`deferred`)
- `table-geometry-core` (`candidate`)
- `unicode-text-repair-width` (`candidate`)
- `charset-encoding-core` (`candidate`)
- `json-json5-document-core` (`candidate`)
- `archive-compression-streams` (`deferred`)

The accepted native support rows improve conversion readiness, but they do not
remove the Haskell upstream-runner build gate or justify a PHP pass-count or
mapped-denominator change for this audit.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
upstream checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` and record a
non-mutating dependency plan for `test-pandoc` and `test-pandoc-lua-engine`
from that exact checkout. Only after that plan is available and stable should a
separate runner slice attempt any bounded Haskell test executable build or
focused upstream runner execution.

## Verification

- PHP syntax check: not applicable, no PHP files changed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 7 test files, 2,821
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
