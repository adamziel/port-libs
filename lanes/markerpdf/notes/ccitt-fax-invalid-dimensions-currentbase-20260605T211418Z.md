# markerPDF CCITT Fax Invalid Dictionary Dimension Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T211418Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T211418Z`
Base accepted HEAD: `baa2332db42f140d1399a4e39f1c24ed61f223f6`

## Source-Truth Boundary

Upstream `sddai/markerPDF` keeps searchable text extraction separate from raster image rendering. In this no-GPU native PHP lane, CCITT Fax image bytes remain review-only while filter, DecodeParms, and geometry metadata are exposed before any future raster backend.

For PDF image dictionaries, `/Width` is not usable as image geometry when it is less than `1`. This slice also treats negative `/Height` as unusable while preserving the existing accepted `/Height 0` unbounded-row behavior used by current CCITT Group 4 fixtures. When the dictionary geometry is unusable, valid CCITT `/DecodeParms /Columns` and positive `/Rows` become the effective review geometry, and the raw dictionary values remain visible as mismatch metadata.

## Native Behavior Added

- `PdfImageRenderer::imageColorSpaceSoftMaskPlan()` and inline image review now ignore invalid dictionary `/Width 0` and negative `/Height` for CCITT effective geometry.
- `PdfTextExtractor::extractImageXObjectBoundaryReview()` mirrors the same CCITT fallback so XObject filter metadata reports `effective_width` and `effective_height` from DecodeParms rather than trusting unusable dictionary dimensions.
- Raw dictionary values are preserved in `dictionary_width`, `dictionary_height`, `columns_match_width`, `rows_match_height`, and `dimension_mismatch`.
- CCITT raster payload remains excluded from visible WordPress text for the XObject smoke.

## Evidence

Red-first focused run after adding the new test, before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL falls back to CCITT DecodeParms geometry when image dictionary dimensions are invalid
Values are not identical
Expected: 16
Actual: 0

1 test files, 611 assertions, 1 failures
```

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS falls back to CCITT DecodeParms geometry when image dictionary dimensions are invalid

1 test files, 642 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-invalid-dimensions-currentbase.php
```

The smoke emits `inline_dictionary_width=0`, `inline_dictionary_height=-1`, `inline_effective_width=16`, `inline_effective_height=2`, `xobject_effective_width=16`, `xobject_effective_height=2`, `xobject_width_source=decodeparms_columns`, `xobject_height_source=decodeparms_rows`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `2219 -> 2220`.
- Focused `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `1 test files / 642 assertions / 0 failures`.
- WordPress scenarios: `1911 -> 1912`.
- Manifest row reused: existing CCITT current-base filter boundary mapping covers this native parser/review boundary; no broad manifest denominator edit was needed.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, malformed/unresolved DecodeParms fail-closed handling, null filter DecodeParms alignment, Flate/Crypt/LZW/RunLength native-prefix stream ownership, direct EOFB/RTC row ownership, escaped keys, nested masks, CCF alias preservation, or post-CCITT filter metadata. The new behavior is limited to invalid dictionary geometry fallback in CCITT effective review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PDF dictionary parser, image filter planner, CCITT DecodeParms review path, and WordPress smoke renderer. Full CCITT raster parity remains outside the current no-GPU scope; no Python, OCR, model, pypdfium, PIL, or external PDF tool execution was run.
