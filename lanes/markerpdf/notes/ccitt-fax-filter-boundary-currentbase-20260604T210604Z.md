# markerPDF CCITT Fax Inline Image Review Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260604T210604Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260604T210604Z`
Base accepted HEAD: `6997f9b596ddbcf1976106359351f857bcb6acba`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through pdftext/PDFium text extraction and routes image regions through `marker/pdf/images.py::render_image()` before RGB output. The native no-GPU port does not decode CCITT Fax raster bytes, but it must preserve image filter intent and keep inline image payloads out of visible WordPress paragraphs.

This slice extends the already accepted CCITT XObject DecodeParms review boundary to inline images: `/CCITTFaxDecode` and abbreviated `/CCF` inline-image filters now get an inline-specific review-only note alongside the existing generic CCITT image-filter review metadata.

## Native Behavior Added

`PdfImageRenderer::inlineImageReviewPlan()` now emits `inline_ccitt_fax_image_filter_review_only` whenever the expanded inline image filter list contains `/CCITTFaxDecode` or `/CCF`. The plan still records the canonical filter, aligned DecodeParms fields, review-only filter list, and payload-excluded metadata without claiming native raster decode.

The existing text-extraction and XObject behavior is unchanged: CCITT streams remain `native_raster_decode=false`, `decoded_with_current_filters=false`, and image payload bytes are excluded from visible text.

## Evidence

Red-first focused run after adding the inline assertion, before the renderer note:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
FAIL marks inline CCITT Fax image filters review-only before WordPress image preview
String does not contain 'inline_ccitt_fax_image_filter_review_only'
...
1 test files, 50 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks inline CCITT Fax image filters review-only before WordPress image preview

1 test files, 51 assertions, 0 failures
```

Renderer/filter regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 579 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `inline_image_filters=["CCITTFaxDecode"]`, `inline_review_only_filters=["CCITTFaxDecode"]`, `inline_ccitt_review_only=true`, `inline_ccitt_note=inline_ccitt_fax_image_filter_review_only`, `image_only_filter_skipped=true`, and no Python/model/external PDF tool execution.

Syntax and status checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, flags: JSON_THROW_ON_ERROR); echo "lane-status.json OK\n";'
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1091 -> 1092`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `40 -> 51`.
- WordPress scenario count: `1091 -> 1092`.

## Non-Overlap

This does not repeat the accepted 2026-06-03 CCITT image XObject DecodeParms boundary, accepted stream-level CCITT text-extraction exclusion, Separation/DeviceN CCITT preview-filter metadata, DCT inline JPEG delimiter handling, JPX/JBIG2 inline preview boundaries, or generic inline-image payload exclusion. The new behavior is specifically inline-image review metadata for `/CCITTFaxDecode` and `/CCF` on the current base.

## Dependency Closure

No new support component is needed. This reuses the native inline image dictionary expander, image filter metadata planner, DecodeParms parser, and WordPress smoke renderer. Full CCITT raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, or external PDF tool execution was run.
