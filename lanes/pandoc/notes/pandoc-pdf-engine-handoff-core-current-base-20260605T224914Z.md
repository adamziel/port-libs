# Pandoc PDF Engine Handoff: Page Metadata Streams

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T224914Z`

Base accepted HEAD: `718fd27c4bd5c5cf3bb2a77e5061b76a630e07d5`

## Behavior

- Added bounded produced-PDF page `/Metadata` stream inspection to
  `PdfEngineHandoff` without invoking a renderer or PDF engine.
- The fake-runner PDF byte inspector now walks the page tree in page order,
  resolves indirect `/Metadata` streams, summarizes unfiltered XMP packet
  fields, and reports filtered or oversized page metadata streams as skipped.
- `fakeRun()` now exposes `pdfPageMetadata`, and `fakeRunSequence()` exposes
  `finalPdfPageMetadata` for final-output diagnostics.
- Diagnostics now include page metadata counts, skipped reasons, and title
  counts so WordPress review queues can detect page-level provenance without
  parsing raw PDF bytes.
- The WordPress PDF handoff example now carries a page-level XMP packet in the
  fake final PDF output and includes the summarized page metadata in its JSON
  handoff summary.

This remains a fake-runner diagnostics slice. No Pandoc, TeX, Typst, roff,
browser renderer, external PDF validator, or online service was run.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 515 assertions, 0 failures`
- Syntax:
  `php -l lanes/pandoc/src/PdfEngineHandoff.php`
  - Result: `No syntax errors detected in lanes/pandoc/src/PdfEngineHandoff.php`
- Syntax:
  `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `No syntax errors detected in lanes/pandoc/tests/PdfEngineHandoffTest.php`
- Syntax:
  `php -l lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
  - Result:
    `No syntax errors detected in lanes/pandoc/examples/wordpress-pdf-engine-handoff.php`
- JSON parse:
  `php -r '$files=["lanes/pandoc/lane-status.json","lanes/pandoc/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); } echo "pandoc json ok\n";'`
  - Result: `pandoc json ok`
- Green check:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 522 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`
- Diff whitespace:
  `git diff --check -- lanes/pandoc`
  - Result: no output, pass

## Status Delta

- `lane-status.json` `phpPass`: `1102 -> 1103`.
- `UPSTREAM_TEST_MANIFEST.json` `benchmarkDenominator.mapped`: `1554 -> 1555`.
- PDF engine handoff manifest cases: `10 -> 11`.
- PDF engine handoff manifest assertions: `95 -> 102`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`PdfEngineHandoff` PDF byte inspector and existing bounded XMP summarizer.

Full upstream Pandoc runner parity remains gated on a hydrated Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and Haskell Tasty runner dependency closure. This slice did not shell out to
Pandoc, Word, LibreOffice, zip/unzip, external template engines, TeX/PDF
engines, Haskell test binaries, browser renderers, online validators, online
services, or live provider tests.

## Non-Overlap

This does not alter PDF engine planning, source rendering, SyncTeX, TeX recorder
files, transcripts, catalog XMP/PDF-A summaries, output intents, page geometry,
outlines, fonts, images, form XObjects, page labels, presentation preferences,
named destinations, tagged-structure summaries, optional content layers,
associated files, portfolios, article threads, AcroForm fields, signatures,
active actions, encryption, or upstream-runner dependency audit behavior.

Follow-up PDF slices should keep marked-content property associated files,
`PagePiece` / `PieceInfo`, PDF/A validation, PDF/UA validation, real renderer
execution, and external validator parity separate.
