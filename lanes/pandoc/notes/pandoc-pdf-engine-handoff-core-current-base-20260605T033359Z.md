# Pandoc PDF Engine Handoff Core Current Base

Slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T033359Z`

Base accepted HEAD: `913ad8ea2b636cf7ded5f7103d09494505e6701b`

## Behavior Added

- Extended `PdfEngineHandoff::fakeRun()` with bounded TeX transcript include-graph parsing from supplied fake-runner stdout, stderr, and log sidecar bytes.
- Fake-runner results now expose `engineTranscriptInputFiles`, `engineTranscriptExternalInputFiles`, and `missingEngineTranscriptInputFiles`.
- Local transcript inputs are normalized with the existing handoff path policy; absolute/system TeX paths are preserved as external basenames for review.
- Missing local transcript inputs fail as `missing-engine-transcript-input-file`, which lets WordPress PDF review packets catch incomplete renderer handoff bundles even when no `.fls` recorder file is present.
- `PdfEngineHandoff::fakeRunSequence()` now carries the final transcript input fields through multipass fake-runner summaries.
- Updated `examples/wordpress-pdf-engine-handoff.php` so the WordPress review smoke exposes transcript include-graph fields alongside existing recorder, SyncTeX, bibliography, log, missing-engine, and produced-PDF diagnostics.

## Source Truth

- Uses the accepted static Pandoc inventory plus the `pandoc-pdf-engine-handoff-core` lane contract as source truth.
- This ports a bounded PDF handoff diagnostics contract only: preserve renderer transcript include evidence for review queues without running TeX, Typst, browser, roff, or PDF engines.
- It does not implement or invoke Pandoc, TeX/PDF engines, Typst, browser layout/rendering, roff, BibTeX, Biber, external template engines, Haskell test binaries, archive tools, office tools, or online services.

## Verification

Baseline focused check before the slice:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 237 assertions, 0 failures.

Focused verification after implementation:

- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  passed: 1 test file, 250 assertions, 0 failures.
- `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  passed: `pdf engine handoff self-test ok`.
- `php tools/run-tests.php lanes/pandoc/tests`
  passed: 19 test files, 6466 assertions, 0 failures.
- `php tools/run-tests.php lanes/pandoc/tests | rg -c '^PASS '`
  passed: `579`.
- `php -l lanes/pandoc/src/PdfEngineHandoff.php`,
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`, and
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php` passed.
- `php -r 'json_decode(file_get_contents("lanes/pandoc/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "pandoc json ok\n";'`
  passed: `pandoc json ok`.
- `git diff --check -- lanes/pandoc` passed.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat prior PDF engine-family argv mapping, template/header/
resource source handoff, source-artifact validation, resource-file validation,
expected ordinary TeX sidecar inventory, engine warning/error log extraction,
missing renderer executable triage, bibliography sidecar classification,
generated PDF output byte/path/page metrics, produced PDF page-tree/outline
inspection, SyncTeX/source-map extraction, TeX recorder `.fls` dependency
parsing, or multipass rerun-state aggregation.

The new surface is TeX-style transcript include-graph diagnostics from supplied
fake-runner log text, including local/external input classification and missing
local input failure without requiring `.fls`.

It also does not touch ZIP/OPC, archive compression, doctemplates, DOCX/ODT,
EPUB3, table geometry, CSL/BibTeX parsing, math/TeX conversion,
charset/Unicode, XML/HTML5 DOM, syntax highlighting, or legacy DOC/CFB
behavior.

## Dependency Closure

No new external support component is needed. This extends the existing native
PHP `PdfEngineHandoff` support component and reuses the accepted fake-runner
file-map/result contract. Real PDF rendering, full PDF parsing, robust TeX
transcript parsing for every possible quoted or space-containing path, real
executable discovery, real `.fls` generation, real SyncTeX generation, real
bibliography execution, remote resource fetching, and renderer sandbox
execution remain intentionally out of scope.

Full upstream Pandoc runner parity remains blocked by the missing hydrated
Pandoc checkout and Haskell Cabal dependency closure already recorded in lane
status.
