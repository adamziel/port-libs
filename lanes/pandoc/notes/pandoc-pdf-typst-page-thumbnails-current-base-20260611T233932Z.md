# PDF/Typst page thumbnail provenance current-base slice

Bead: plib-k8p65
Base: current main e9d25106ae
Date: 2026-06-11 UTC

## Scope

Added produced-PDF `/Thumb` page thumbnail provenance to `PdfEngineHandoff` without invoking Pandoc, Typst, TeX/PDF engines, browser renderers, external validators, online services, live provider tests, or live-service provider tests.

The fake-run inspection now exposes:

- `pdfPageThumbnails` with page object, thumbnail object, value kind, image subtype validity, dimensions, color space, filters, interpolation/mask metadata, bounded stream bytes/hash/skip state, review status, and issues.
- `pdfPageThumbnailPolicy` summarizing pages with thumbnails, valid image thumbnails, missing objects, non-image thumbnails, missing/skipped streams, color spaces, filters, and issue labels.
- Sequence propagation through `finalPdfPageThumbnails` and `finalPdfPageThumbnailPolicy`.

## Focused Coverage

Added one `PdfEngineHandoffTest` fixture covering:

- valid image `/Thumb` stream hashing and metadata extraction;
- non-image thumbnail target review;
- missing thumbnail object review;
- diagnostics and final fake-run sequence payloads.

## Verification

- `php -l lanes/pandoc/src/PdfEngineHandoff.php`
- `php -l lanes/pandoc/tests/PdfEngineHandoffTest.php`
- `php tools/run-tests.php lanes/pandoc/tests/PdfEngineHandoffTest.php`
  - 1 test file, 1865 assertions, 0 failures
- `php tools/run-tests.php lanes/pandoc/tests`
  - 44 test files, 67306 assertions, 0 failures
