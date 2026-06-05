# Pandoc PDF Engine Handoff: Associated-File Sources

Micro-slice: `pandoc-pdf-engine-handoff-core-current-base-20260605T211619Z`

Base accepted HEAD: `baa2332db42f140d1399a4e39f1c24ed61f223f6`

## Behavior

- Added bounded produced-PDF `/AF` associated-file provenance for page and
  structure-element containers in `PdfEngineHandoff`.
- Page-level associated files are now reported with `page:<ref>.AF` sources,
  and tagged-structure associated files are reported with `structure:<ref>.AF`
  sources before the generic `filespec:<ref>` fallback.
- The WordPress PDF handoff example now includes page supplemental notes and
  structure source-data attachments so review queues can distinguish PDF/A-style
  associated files from generic embedded file inventory.

This remains a fake-runner diagnostics slice. No renderer or converter was run.

## Evidence

- Baseline:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 507 assertions, 0 failures`
- Red check after adding the focused test:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 510 assertions, 1 failures`
  - Failure: page and structure `/AF` files were reported as
    `filespec:*` sources.
- Green check after implementation:
  `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - Result: `1 test files, 515 assertions, 0 failures`
- Example smoke:
  `php lanes/pandoc/examples/wordpress-pdf-engine-handoff.php --self-test`
  - Result: `pdf engine handoff self-test ok`

## Dependency Closure

No new support component is needed. This slice reuses the native PHP
`PdfEngineHandoff` fake-runner PDF byte inspector and the existing WordPress
PDF handoff example.

Full upstream Pandoc runner parity remains gated on a hydrated Pandoc checkout
at `0640c4c9859aa5a3ede082c190fcd5883c24ac83` with Cabal project/package files
and Haskell Tasty runner dependency closure. No Pandoc, Cabal build, Haskell
runner, TeX/PDF engine, Typst, roff, browser renderer, JavaScript, external PDF
validator, online sanitizer, online service, or live provider test was executed.

## Non-Overlap

This does not alter PDF engine planning, source rendering, SyncTeX, TeX recorder
files, transcripts, XMP/PDF-A, output intents, page boxes, outlines, fonts,
images, form XObjects, page labels, presentation preferences, named
destinations, tagged-structure summaries, optional content layers, portfolios,
article threads, AcroForm fields, signatures, active actions, encryption, or
upstream-runner dependency audit behavior.

Follow-up PDF slices should keep page metadata streams, marked-content property
associated files, richer attachment relationship policies, PDF/A validation,
and full upstream Pandoc Haskell runner parity separate.
