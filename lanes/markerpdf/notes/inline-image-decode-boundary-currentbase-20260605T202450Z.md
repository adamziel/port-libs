# Inline Image Decode Boundary Currentbase - 2026-06-05T20:24:50Z

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T202450Z`
- Accepted base: `f43815ee1e5ef6d6a036ece46034470074748943`
- Source truth: upstream markerPDF keeps searchable text extraction separate from image/OCR/model paths; under the no-GPU markerPDF scope, raw inline raster bytes must not become Gutenberg paragraph text.

## Behavior Added

Raw JPX inline image candidates that begin with the JPEG 2000 SOC marker now treat EOC (`ff d9`) as a complete boundary only when bytes after EOC are whitespace. If malformed post-EOC surplus bytes contain a fake `EI BT ... Tj ET` sequence, the parser keeps those bytes image-owned until the real inline-image `EI` delimiter instead of leaking them into text runs.

This is intentionally non-overlapping with the accepted Flate-wrapped JPX no-sample-floor boundary and the tight DCT/JPX preview terminator slice. The new case is direct raw `/JPXDecode` inline image data with post-EOC non-whitespace surplus before the real `EI`.

## Evidence

- Red-first focused run after adding the case:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 498 assertions, 1 failures`
  - Failure: `JPX Post EOC Inline Noise` leaked into extracted text runs.
- Focused run after parser update:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 506 assertions, 0 failures`
- Focused inline-image regression:
  - `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php`
  - Result: `7 test files, 977 assertions, 0 failures`
- Syntax:
  - `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
  - `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` passed.
  - `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` passed.
- WordPress smoke:
  - `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php > /tmp/markerpdf-inline-image-decode-boundary-currentbase.html` passed.
  - Metadata check result: `inline image smoke metadata ok`
- Whitespace:
  - `git diff --check -- lanes/markerpdf` passed.

## WordPress Scenario

`lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` now includes a raw JPX inline image whose post-EOC surplus contains fake text. The smoke metadata verifies `jpx_post_eoc_surplus_payload_excluded_until_real_ei`, `visible_text_imported`, and `excluded_inline_image_text` without executing Python, OCR, models, PDFium, or external PDF tools.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF content-stream tokenizer and inline image boundary logic. GPU/model/OCR parity remains intentionally out of scope under the current markerPDF no-GPU direction.

## Next Task

Continue with non-overlapping native searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
