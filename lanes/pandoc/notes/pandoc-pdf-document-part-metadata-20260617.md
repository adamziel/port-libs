# PDF document part metadata slice

Bead: `plib-ibcv7`
Base: `c2e7fad098`
Date: 2026-06-17 UTC

## Scope

Added bounded produced-PDF catalog `/DPartRoot` document part metadata extraction to `PdfEngineHandoff`. Fake-run inspection now exposes `pdfDocumentPartMetadata` with root and child document part nodes, `/DPM` metadata keys/items, start/end page references, child references, missing child references, and depth. The sequence result propagates the final inventory through `finalPdfDocumentPartMetadata`; `pdfDocumentPartPolicy` summarizes node, metadata, child-reference, missing-child, and max-depth counts.

This is limited to native produced-byte PDF inspection in `lanes/pandoc`; it does not invoke Pandoc, Typst, TeX/PDF engines, browser renderers, office suites, external validators, online services, live provider tests, or live-service provider tests.

## Coverage

Added one focused `PdfEngineHandoffTest` fixture covering catalog `/DPartRoot` with referenced, inline, nested, and missing document part children plus referenced and inline `/DPM` dictionaries.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 2853 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 258 test files, 175242 assertions, 0 failures

Ledger accounting adds `mappedPdfDocumentPartMetadataCases = 1` and `pdfDocumentPartMetadataAssertions = 14`, moving `phpPass` 16994 -> 16995 with `phpFail` still at 0.
