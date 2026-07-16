# markerPDF CCITT Fax Inline DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T002103Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T002103Z`
Base accepted HEAD: `9b1ef263ff3924c6fe0e7eac819c5983af847fea`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps visible searchable PDF text on the pdftext/PDFium text-page path and routes image pixels through `marker/pdf/images.py::render_image()` / `render_bbox_image()` before RGB image handoff. Under the no-GPU/no-model lane scope, the native PHP port does not decode CCITT Fax raster data, but it must preserve image-filter intent and make invalid CCITT `/DecodeParms` reviewable without leaking fax bytes into WordPress paragraphs.

## Native Behavior Added

`PdfImageRenderer::inlineImageReviewPlan()` now uses the same CCITT DecodeParms review shape as the native Image XObject boundary:

- malformed `/K`, `/Columns`, `/Rows`, boolean operands, and `/DamagedRowsBeforeError` are recorded in ordered `invalid_decode_parms_fields`;
- invalid rows get `valid_decode_parms=false` and `decode_parms_review=invalid_ccitt_decodeparms_fail_closed`;
- `/CCITTFaxDecode` and `/CCF` remain preview-only, `native_raster_decode=false`, and inline image payload bytes stay excluded from visible text and review JSON.

This is specifically the inline renderer metadata path. It does not repeat the accepted stream-level CCITT payload exclusion, valid Image XObject CCITT DecodeParms review, malformed Image XObject DecodeParms fail-closed review, or the earlier inline CCITT review-only note.

## Evidence

Red-first focused run after adding the inline invalid DecodeParms assertion, before the renderer fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
PASS marks inline CCITT Fax image filters review-only before WordPress image preview
FAIL marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview
...
Actual image_filter_details lacked valid_decode_parms=false, invalid_decode_parms_fields, and decode_parms_review.
1 test files, 76 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
PASS marks inline CCITT Fax image filters review-only before WordPress image preview
PASS marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview

1 test files, 82 assertions, 0 failures
```

Adjacent renderer/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 633 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `inline_invalid_decode_parms_valid=false`, `inline_invalid_decode_parms_fields=["k","columns","rows","black_is_1","end_of_line","damaged_rows_before_error"]`, `inline_invalid_payload_excluded_from_review=true`, `image_only_filter_skipped=true`, and no Python/model/external PDF tool execution.

Syntax/JSON checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file, " OK\n"; }'
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1176 -> 1177`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `63 -> 82`.
- WordPress scenarios: `1162 -> 1163`.

## Dependency Closure

No new support component is needed. This reuses the native inline image dictionary expander, image filter metadata planner, DecodeParms parser, and WordPress smoke renderer. Full CCITT raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; no Python, OCR, model, external PDF tool, pypdfium, or PIL execution was run.
