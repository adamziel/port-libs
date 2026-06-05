# markerPDF CCITT Fax Null-Filter DecodeParms Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260605T030642Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260605T030642Z`
Base accepted HEAD: `ddcf206af6d96f39c283c9cb57b47988ee857ab3`

## Source-Truth Boundary

Upstream `sddai/markerPDF` at the manifest-pinned commit keeps searchable PDF text extraction separate from raster image rendering. CCITT Fax image bytes belong to the image-rendering path, not WordPress paragraph text, and this no-GPU native PHP lane keeps those bytes review-only while exposing parser metadata accurately before any future raster backend.

PDF filter arrays can contain `null` entries, and `/DecodeParms` arrays align with the corresponding filter-array slots. For an inline image such as `/Filter [null /CCF] /DecodeParms [null << ... >>]`, the CCITT preview filter must use the second DecodeParms dictionary rather than falling back to CCITT defaults.

## Native Behavior Added

`PdfImageRenderer` now preserves null filter-array slots internally while building image filter metadata. Public `image_filters` output remains the existing non-null filter list, but DecodeParms lookup now uses the original filter slot index or the existing compact-array fallback when appropriate.

This maps inline CCITT/CCF preview metadata where:

- `/Filter [null /CCF]` keeps the second `/DecodeParms` dictionary aligned to CCITT;
- effective CCITT fields use the supplied `K`, `Columns`, `Rows`, `BlackIs1`, and `EndOfBlock` values;
- omitted CCITT fields still record defaults separately;
- fax payload bytes remain excluded from visible text and review JSON;
- native raster decode remains false.

## Evidence

Red-first ad-hoc probe before the source edit:

```text
php -r 'require "tools/bootstrap.php"; ... inlineImageReviewPlan("/W 16 /H 2 /IM true /F [null /CCF] /DP [null << /K -1 /Columns 16 /Rows 2 /BlackIs1 true /EndOfBlock false >>]", ...)'
```

The probe reported `decode_parms => NULL`, `decode_parms_present=false`, and default effective CCITT `columns=1728` instead of the supplied `columns=16`.

Focused gate after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS aligns CCITT Fax DecodeParms arrays after null filter entries before RGB preview

1 test files, 146 assertions, 0 failures
```

Adjacent image/filter gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 918 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-filter-import.php
```

The smoke emits `inline_null_filter_decode_parms_aligned=true`, `inline_null_filter_payload_excluded_from_review=true`, `inline_null_filter_dimension_mismatch=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- Focused PASS cases: `1333 -> 1334`.
- Focused assertions in `PdfCcittFaxFilterBoundaryCurrentBaseTest.php`: `139 -> 146`.
- WordPress scenarios: `1282 -> 1283`.
- Manifest current-base CCITT boundary behaviors: `1 -> 2`.

## Non-Overlap

This does not repeat accepted CCITT image-only stream exclusion, raw DecodeParms extraction, malformed DecodeParms fail-closed metadata, effective DecodeParms geometry metadata, escaped DecodeParms key lookup, inline CCITT review-only notes, inline invalid DecodeParms review, Flate-prefix CCITT boundary recovery, direct EOFB/RTC ownership, DCT/JPX/JBIG2 preview-only image filters, or generic inline image payload exclusion. The new bounded behavior is specifically CCITT DecodeParms alignment when a filter array contains `null` entries before the CCITT filter in renderer preview planning.

## Dependency Closure

No new support component is needed. This reuses the native PDF inline-image dictionary expander, image filter metadata planner, CCITT DecodeParms review builder, and WordPress smoke renderer. Full CCITT raster parity remains gated on PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, external PDF tool, or live-service provider execution was run.
