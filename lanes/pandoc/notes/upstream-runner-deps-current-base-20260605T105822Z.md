# Pandoc Upstream Runner Dependency Audit 2026-06-05

## Scope

Micro-slice: `pandoc-upstream-runner-deps-current-base-20260605T105822Z`.

Accepted base: `ae7980d439439707292252a5e771e15fd3153fb9`.

This is an upstream-runner dependency audit slice, not a native conversion
behavior slice. No Pandoc binary, Cabal solver/build/test command, Haskell test
binary, Word, LibreOffice, `zip`/`unzip`, `tar`, `lz4`, external template
engine, TeX/PDF engine, MathJax, KaTeX, Typst, browser renderer, roff
renderer, media player, online conversion service, online sanitizer, or other
external converter was executed as progress.

## Current-Base Evidence

- No current `port-pandoc-*.needs-lane-rework.md` note was present under
  `/home/claude/port-libs/.tmux-team/tmp/handoff-candidates`.
- This isolated worktree started clean at accepted base
  `ae7980d439439707292252a5e771e15fd3153fb9`.
- Filename searches under `/home/claude/port-libs/.upstream-cache` and this
  isolated worktree found no `pandoc.cabal`,
  `pandoc-lua-engine.cabal`, `cabal.project`, `cabal.project.freeze`,
  `test-pandoc.hs`, `test-pandoc-lua-engine.hs`, or `stack.yaml` source files.
- `ghc` is available as version 9.10.3 and `cabal-install` is available as
  version 3.12.1.0. `stack` and `pandoc` were not found on `PATH`.
- The raw-source Cabal closure recorded by
  `upstream-runner-deps-current-base-20260604T170433Z.md` remains the accepted
  lane-local source truth for the runner dependency shape at upstream commit
  `0640c4c9859aa5a3ede082c190fcd5883c24ac83`.

## Added Native Audit Gate

This slice adds `PortLibs\Pandoc\UpstreamRunnerDependencyAudit`, a native PHP
helper that makes the upstream Haskell runner gate deterministic and testable
without invoking Cabal or Pandoc. The helper checks:

- Required upstream files:
  `cabal.project`, `pandoc.cabal`,
  `pandoc-lua-engine/pandoc-lua-engine.cabal`, `test/test-pandoc.hs`, and
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`.
- Required Cabal toolchain commands: `ghc` and `cabal`.
- Runner targets and entry points:
  `test:test-pandoc` from `pandoc.cabal` / `test/test-pandoc.hs`, and
  `test:test-pandoc-lua-engine` from
  `pandoc-lua-engine/pandoc-lua-engine.cabal` /
  `pandoc-lua-engine/test/test-pandoc-lua-engine.hs`.
- Exact `cabal.project` Git source-repository pins:
  `doclayout`, `typst-symbols`, `typst-hs`, `texmath`, and `citeproc`.

The helper reports `readyForNonMutatingCabalPlan` only when the checkout files,
toolchain, and exact Git pins are all present. In the current environment it
reports:

```json
{
    "ready": false,
    "missingFiles": [
        "cabal.project",
        "pandoc.cabal",
        "pandoc-lua-engine/pandoc-lua-engine.cabal",
        "test/test-pandoc.hs",
        "pandoc-lua-engine/test/test-pandoc-lua-engine.hs"
    ],
    "missingTools": [],
    "missingPins": [
        "doclayout",
        "typst-symbols",
        "typst-hs",
        "texmath",
        "citeproc"
    ],
    "mismatchedPins": []
}
```

## Current Activation Decision

No safe local upstream-runner build step is available in this worktree. The
toolchain binaries needed for a future Cabal plan are present, but the hydrated
Pandoc checkout and its `cabal.project`, `pandoc.cabal`,
`pandoc-lua-engine/pandoc-lua-engine.cabal`, runner entry points, and exact Git
source-repository pins are absent. Running a Cabal solver or build from this
isolated lane would require hydrating or fetching the broad upstream checkout
plus resolving and building the Haskell dependency graph before a
non-mutating solver/build plan could be recorded.

This keeps the full upstream runner gate open. It does not block accepted
native PHP conversion slices for Markdown/HTML, XML/HTML5 DOM, ZIP/OPC, YAML,
CSL/BibTeX, DOCX/ODT, EPUB3, table geometry, math/TeX, PDF handoff planning,
archive compression streams, charset/Unicode support, doctemplates, syntax
highlighting, or legacy DOC/CFB.

## Dependency-Backlog Decision

No new native PHP format support component is activated by this audit. The new
helper is lane-local runner-dependency evidence, not a converter primitive.
Existing bounded support rows remain the correct lane-local dependency path for
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
surfaces. It claims one lane-local runner-dependency audit mapping and three
PHP PASS cases, not upstream runner parity.

## Next Activation Gate

Before claiming upstream runner dependency closure, hydrate a local Pandoc
checkout at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with `cabal.project`,
`pandoc.cabal`, `pandoc-lua-engine/pandoc-lua-engine.cabal`,
`test/test-pandoc.hs`, and
`pandoc-lua-engine/test/test-pandoc-lua-engine.hs` present. Then verify the
exact `doclayout`, `typst-symbols`, `typst-hs`, `texmath`, and `citeproc`
source-repository pins and record a non-mutating Cabal solver/build plan for
`test:test-pandoc` and `test:test-pandoc-lua-engine`. Only after that plan is
stable should a separate runner slice attempt any bounded Haskell test
executable build or focused upstream runner execution.

## Verification

- PHP syntax checks:
  `php -l lanes/pandoc/src/UpstreamRunnerDependencyAudit.php && php -l lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `No syntax errors detected` for both changed PHP files.
- Focused audit helper test:
  `php tools/run-tests.php lanes/pandoc/tests/UpstreamRunnerDependencyAuditTest.php`
  - `1 test files, 27 assertions, 0 failures`
- Focused Pandoc lane directory:
  `php tools/run-tests.php lanes/pandoc/tests`
  - `21 test files, 10572 assertions, 0 failures`
- Focused PASS-line count:
  `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS'`
  - `839`
- JSON validation:
  `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  - `pandoc json ok`
- Example smoke: not run - no example added or changed.
- `git diff --check -- lanes/pandoc` passed.
- Root harness: not run - isolated micro-slice.
