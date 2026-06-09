# Pandoc Upstream Runner Dependency Audit 2026-06-04

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260604T173954Z`.

Accepted base: `a3ed21553e0924089dcab2d718afc2adfde26809`.

This is an upstream-runner dependency audit slice with a small native PHP
support helper. No Pandoc binary, Cabal solver/build/test command, Haskell
test binary, Word, LibreOffice, `zip`/`unzip`, external template engine,
TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff renderer, online
conversion service, or other external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree is at accepted base
  `a3ed21553e0924089dcab2d718afc2adfde26809` with no pre-existing dirty
  Pandoc lane changes before this slice.
- The accepted Pandoc lane state records 2,276 upstream test/data/benchmark
  artifacts inspected, 817 mapped native PHP checks, and 360 focused PHP PASS
  lines with 0 failures.
- `/home/claude/port-libs/.upstream-cache` still has no local Pandoc Cabal
  package/project files discoverable by filename search for `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, or `cabal.project.freeze`.
- The accepted `20260604T170433Z` raw-source audit remains the pinned source
  truth for the Cabal closure at upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Native Dependency-Gate Helper

Added `PortLibs\Pandoc\UpstreamRunnerDependencies` to encode the bounded
upstream-runner dependency gate in native PHP:

- required local upstream files: `cabal.project`, `pandoc.cabal`,
  `pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`;
- solver targets: `test:test-pandoc` and `test:test-pandoc-lua-engine`;
- direct `test-pandoc` dependency closure from the pinned Cabal file, including
  the local `pandoc` library and `Diff`, `Glob`, `doctemplates`, `pandoc-types`,
  `tasty`, `tasty-golden`, `tasty-hunit`, `tasty-quickcheck`, `xml`, and
  `zip-archive`;
- direct `test-pandoc-lua-engine` dependency closure, including local
  `pandoc-lua-engine`, `hslua`, `tasty-lua`, `pandoc`, `pandoc-types`, and the
  Lua-engine module dependencies such as `hslua-module-doclayout`,
  `hslua-module-zip`, `lpeg`, and `pandoc-lua-marshal`;
- cabal.project packages, Pandoc flags, project constraints, and Git
  source-repository pins for `doclayout`, `typst-symbols`, `typst-hs`,
  `texmath`, and `citeproc`;
- the existing bounded Pandoc support component list, with
  `dependencyBacklogDecision` fixed as `no-new-native-support-component`.

The helper deliberately returns `willExecute: false`. With no required Cabal
files present, `evaluateLocalGate()` reports
`blocked-missing-upstream-checkout`. When all required files are present, it
reports `plan-ready` for a non-mutating Cabal plan only; runner execution still
belongs to a separate authorized slice.

## Dependency-Backlog Decision

No new native format support component is activated by this audit. The current
blocker is upstream Haskell runner/build dependency closure, not a missing
Pandoc-local PHP conversion primitive. Existing bounded support rows remain the
right dependency path for conversion coverage:

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

## Status Delta

- Added +4 focused PHP PASS lines and +60 focused assertions in
  `UpstreamRunnerDependenciesTest`.
- Manifest mapped checks move from 817 to 821.
- Lane PHP pass count moves from 360 to 364.
- Full upstream runner parity remains blocked until the Pandoc upstream
  checkout is hydrated and a non-mutating Cabal solver/build plan is recorded.

## Next Activation Gate

Hydrate a local Pandoc checkout at
`0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`, and both test
entry points present. Then run the native helper over that file inventory and
record a non-mutating Cabal solver/build plan for `test:test-pandoc` and
`test:test-pandoc-lua-engine`, including resolution policy for the five pinned
Git source-repository packages. Only after that plan is stable should a
separate runner slice attempt any bounded Haskell test executable build or
focused upstream runner execution.

## Verification

- `php -l lanes/pandoc/src/UpstreamRunnerDependencies.php` passed.
- `php -l lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php` passed.
- `python3 -m json.tool lanes/pandoc/lane-status.json` passed.
- `python3 -m json.tool lanes/pandoc/UPSTREAM_TEST_MANIFEST.json` passed.
- `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependenciesTest.php`
  passed: 1 test file, 60 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 12 test files, 3,352
  assertions, 0 failures.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
