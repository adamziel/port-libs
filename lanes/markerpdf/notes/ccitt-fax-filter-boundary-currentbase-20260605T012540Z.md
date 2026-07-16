# markerPDF CCITT Fax Effective DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T012540Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T012540Z`
Base accepted HEAD: `c0afeab573c7ee1ef1cf900a1f4e33962e9c0b34`

## Source-Truth Boundary

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from raster image rendering. Image pixels are routed through PDFium/PIL-backed RGB handoff in `marker/pdf/images.py`, while this no-GPU native PHP lane keeps CCITT Fax raster bytes review-only.

PDF CCITT Fax `/DecodeParms` have meaningful defaults (`K=0`, `Columns=1728`, `Rows=0`, `BlackIs1=false`, `EncodedByteAlign=false`, `EndOfLine=false`, `EndOfBlock=true`, `DamagedRowsBeforeError=0`). This slice exposes those effective values and image-dictionary versus `/Columns` and `/Rows` geometry decisions before any future native raster backend runs.

## Native Behavior Added

`PdfImageRenderer::imageColorSpaceSoftMaskPlan()` and `PdfTextExtractor::extractImageXObjectBoundaryReview()` now expose `ccitt_fax_decode_boundary` metadata for `/CCITTFaxDecode` and `/CCF` filters:

- raw `filter_details` remain unchanged for existing callers;
- effective CCITT defaults are recorded separately from raw operands;
- invalid DecodeParms fields remain fail-closed and fall back to effective defaults for review;
- dictionary `/Width` and `/Height` stay authoritative when present;
- missing dictionary geometry can still report review-only `effective_width` from `/Columns` and `effective_height` from positive `/Rows`;
- width/column and height/row mismatches are review metadata only;
- native raster decode remains false and fax payload bytes stay out of visible WordPress text.

## Evidence

Red-first focused run after adding the new boundary assertions, before source changes:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
PASS marks inline CCITT Fax image filters review-only before WordPress image preview
PASS marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview
PASS keeps Flate-wrapped CCITT Fax endstream decoys inside image payload boundaries
PHP Warning:  Undefined array key "ccitt_fax_decode_boundary" ...
FAIL records effective CCITT Fax DecodeParms defaults and geometry boundaries before RGB preview
1 test files, 101 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records CCITT Fax image DecodeParms without rasterizing or leaking payload text
PASS marks malformed CCITT Fax DecodeParms fail closed without treating them as defaults
PASS marks inline CCITT Fax image filters review-only before WordPress image preview
PASS marks malformed inline CCITT Fax DecodeParms fail closed before RGB preview
PASS keeps Flate-wrapped CCITT Fax endstream decoys inside image payload boundaries
PASS records effective CCITT Fax DecodeParms defaults and geometry boundaries before RGB preview

1 test files, 109 assertions, 0 failures
```

Adjacent renderer/text/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1312 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `inline_effective_decode_parms`, `inline_geometry_dimension_mismatch=true`, `inline_geometry_defaults_applied`, `xobject_geometry_effective_width=16`, `xobject_geometry_effective_height=4`, `xobject_geometry_width_source=decodeparms_columns`, `xobject_geometry_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1239 -> 1240`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `82 -> 109`.
- WordPress scenarios: `1211 -> 1212`.
- Manifest current-base CCITT boundary behaviors: `1 -> 2`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, XObject raw DecodeParms extraction, malformed DecodeParms fail-closed marking, inline CCITT review-only notes, inline invalid DecodeParms review, stale Flate-prefix stream-length recovery, DCT/JPX/JBIG2 preview-only filters, or generic inline image payload exclusion. The new behavior is effective CCITT default and geometry-source metadata on the current base.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary parser, image filter metadata planner, DecodeParms parser, and WordPress smoke renderer. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, or external PDF tool execution was run.
