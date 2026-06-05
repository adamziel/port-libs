# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260604T235851Z`

Base accepted HEAD: `43d6c6085912b0a2e7f68f49d9869c535f444985`

## Behavior Added

- Added `PdfEngineHandoff::fakeRunSequence()` for bounded multipass PDF
  fake-runner handoff diagnostics.
- The sequence delegates each attempt to the existing `fakeRun()` validator, so
  source staging, required template/header artifacts, expected sidecars,
  log warning/error extraction, output metrics, PDF hash, byte-count checks,
  and truncated/stale PDF diagnostics stay centralized.
- The sequence reports attempt count, successful attempts, final output hash
  and metrics, aggregated warnings/errors, failed-attempt reasons, final
  rerun-needed state, and rerun-cleared diagnostics.
- Bounded attempt validation rejects empty attempt lists, more than eight fake
  attempts, and non-array attempt records before any renderer execution path
  can be implied.
- Updated `examples/wordpress-pdf-engine-handoff.php` so WordPress review
  packets expose a first pass with citation/rerun warnings plus a final fake
  pass whose output clears the rerun state.

## Source Truth

- Uses the accepted Pandoc static inventory plus the existing
  `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports the PDF handoff contract for multipass TeX-style workflows:
  capture each renderer attempt as data, preserve final PDF metrics, and keep
  unresolved rerun requirements explicit for import review queues.
- It does not implement TeX, Typst, browser layout, roff, PDF rendering,
  Pandoc execution, external template rendering, or upstream Haskell runner
  parity.

## Verification

Red-first focused check before implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  failed: 1 test file, 116 assertions, 3 failures. The new multipass tests
  failed because `PdfEngineHandoff::fakeRunSequence()` did not exist.

Final focused verification:

- `php -l lanes/pandoc/src/PdfEngineHandoff.php` passed.
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php` passed.
- `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 142 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests` passed: 17 test files, 4,237
  assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR);'`
  passed.
- `git diff --check -- lanes/pandoc` passed.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, expected sidecar
inventory, warning/error log extraction, single-pass rerun-needed extraction,
or generated PDF artifact validation against declared output metrics. The new
surface is bounded multipass fake-runner aggregation and final rerun-state
handoff.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX, math/TeX conversion, charset/Unicode, XML/
HTML5 DOM, or legacy DOC/CFB behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted Pandoc AST,
`MarkdownReader`, and `LatexWriter` surfaces. Real PDF rendering remains
intentionally out of scope: TeX, Typst, browser, roff, and PDF engines must
stay in separate execution environments and are not activated by this lane
patch.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.

Root harness: not run - isolated micro-slice.
