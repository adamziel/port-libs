# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T120159Z`.

Accepted base: `2887ce8ff48f46f6481d6e6605791001c76e2132`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`, external template
engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff
renderer, media player, online conversion service, online sanitizer, or other
external converter was executed as progress.

## Current-Base Evidence

- No current top-level `port-pandoc-*.needs-lane-rework.md` note was present
  under `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`. Only old
  May notes under the `stale/` subdirectory matched the broader filename
  pattern.
- This isolated worktree started clean at accepted base
  `2887ce8ff48f46f6481d6e6605791001c76e2132`.
- `/home/claude/port-libs/.upstream-cache/pandoc` is absent, and the shallow
  cache search found no hydrated Pandoc checkout directory.
- `ghc --numeric-version` reports `9.10.3`; `cabal --numeric-version` reports
  `3.12.1.0`. `stack` and `pandoc` are not on `PATH`.
- The accepted raw-source runner dependency shape remains the one recorded by
  `upstream-runner-deps-current-base-20260604T170433Z.md` for upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Added Native Audit Gate

This slice extends `PortLibs\Pandoc\UpstreamRunnerDependencyAudit` so a future
hydrated checkout cannot be marked ready for a non-mutating Cabal plan merely
because the expected filenames and Git source pins exist.

The helper now also checks:

- `cabal.project` package entries for `.`, `pandoc-lua-engine`,
  `pandoc-server`, and `pandoc-cli`.
- `cabal.project` `package pandoc` flags `+embed_data_files` and `+http`.
- Cabal `test-suite` stanzas for `test-pandoc` and
  `test-pandoc-lua-engine`, including `main-is` and `hs-source-dirs`.
- Imported `common` stanza closure for `build-depends`.
- Direct runner `build-depends` for `test:test-pandoc`, including `base`,
  local `pandoc`, `Diff`, `Glob`, `tasty`, `tasty-golden`, `tasty-hunit`,
  `tasty-quickcheck`, `xml`, and `zip-archive`.
- Direct runner `build-depends` for `test:test-pandoc-lua-engine`, including
  `base`, local `pandoc-lua-engine`, `hslua`, `pandoc`, `pandoc-types`,
  `tasty`, `tasty-golden`, `tasty-hunit`, and `tasty-lua`.

The audit still reports `readyForNonMutatingCabalPlan` only when required
files, `ghc`, `cabal`, exact `cabal.project` Git source-repository pins,
project package entries, project flags, runner entry points, and direct runner
dependencies are all present.

## Current Activation Decision

No safe local upstream-runner build step is available in this worktree. The
toolchain binaries needed for a future Cabal plan are present, but the hydrated
Pandoc checkout and its `cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry points, project
package/flag closure, and exact Git source-repository pins are absent.

Running a Cabal solver or build from this isolated lane would require
hydrating or fetching the broad upstream checkout plus resolving and building
the Haskell dependency graph before a non-mutating solver/build plan could be
recorded. This keeps the full upstream runner gate open, but it does not block
accepted native PHP conversion slices.

## Dependency-Backlog Decision

No new native PHP format support component is activated by this audit. The
patch is lane-local runner-dependency evidence, not a converter primitive.
Existing bounded support rows remain the correct native dependency path for
real conversion coverage:

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

## Non-Overlap

This audit deliberately avoids native support-library implementation slices,
including current DOCX/OpenXML, EPUB3, ODT/OpenDocument, archive compression,
charset/Unicode, syntax-highlighting, ZIP/OPC, YAML, CSL/BibTeX,
table-geometry, math/TeX, PDF handoff, XML/HTML5 DOM, and legacy DOC/CFB
surfaces. It claims one additional focused PHP PASS case for the runner audit
helper and no upstream-runner parity.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present. Then verify:

- `cabal.project` packages `.`, `pandoc-lua-engine`, `pandoc-server`, and
  `pandoc-cli`.
- Pandoc flags `+embed_data_files` and `+http`.
- Exact Git source-repository pins for `doclayout`, `typst-symbols`,
  `typst-hs`, `texmath`, and `citeproc`.
- Direct runner `build-depends` closure and entry points for
  `test:test-pandoc` and `test:test-pandoc-lua-engine`.

Only after those static gates pass should a separate runner slice record a
non-mutating Cabal solver/build plan or attempt bounded Haskell runner
execution.

## Verification

- Baseline focused audit helper:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - before edits: `1 test files, 27 assertions, 0 failures`
- PHP syntax checks:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php`
  - `No syntax errors detected`
  `php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected`
- Focused audit helper test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 60 assertions, 0 failures`
- Focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `21 test files, 11288 assertions, 0 failures`
- Focused PASS-line count:
  `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - `874`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
