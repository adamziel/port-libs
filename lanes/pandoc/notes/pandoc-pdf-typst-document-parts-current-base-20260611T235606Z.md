# PDF/Typst document-part provenance current-base slice

Bead: plib-02gxp
Base: current main 35768390c9
Date: 2026-06-11 UTC

## Scope

Added produced-PDF document-part provenance to `PdfEngineHandoff` without invoking Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

The fake-run inspection now exposes:

- `pdfDocumentParts` for catalog `/DPartRoot` roots, reachable `/DPart` hierarchy nodes, DPM metadata entries, and page-level `/DPart` associations.
- `pdfDocumentPartPolicy` summarizing roots, part nodes, page references, metadata entries, pages with parts, missing references, unrooted references, parent mismatches, review entries, and issue labels.
- Sequence propagation through `finalPdfDocumentParts` and `finalPdfDocumentPartPolicy`.

## Focused Coverage

Added one `PdfEngineHandoffTest` fixture covering:

- `/DPartRoot` hierarchy traversal;
- inline and referenced `/DPM` metadata normalization;
- page `/DPart` association extraction;
- missing document-part reference review diagnostics;
- final fake-run sequence payloads.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1878 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67456 assertions, 0 failures
