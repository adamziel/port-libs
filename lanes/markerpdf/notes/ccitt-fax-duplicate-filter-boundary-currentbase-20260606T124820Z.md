# CCITT Fax Duplicate Filter Boundary Current Base

Slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260606T124820Z`
Base: `b35137156237456f8b66e635831adbb18f2efbfa`

## Behavior

Upstream markerPDF treats PDF image rasterization as a model/PDFium-backed handoff, but this native no-GPU lane must still make image stream filter boundaries explicit and fail closed. PDF stream dictionaries should not contain duplicate top-level `/Filter` declarations; when they do, native WordPress import review should reject the ambiguous filter operand without decoding CCITT raster bytes or leaking payload text.

This slice adds focused CCITT Fax coverage for `/Filter /FlateDecode /Filter /CCITTFaxDecode`: text extraction preserves surrounding paragraphs, reports `filters_resolved=false`, records `duplicate_filter_declaration_count=1` with `filter_operand_policy=reject_duplicate_filter_declarations`, keeps `CCITTFaxDecode` review-only, and preserves CCITT filter/coding metadata. `PdfImageRenderer` now adds the CCITT-specific note `ccitt_fax_duplicate_filter_declarations_fail_closed` alongside the generic duplicate-filter fail-closed note.

## Evidence

- Baseline focused run before edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `1 test files / 838 assertions / 0 failures`.
- Red-first run after adding the duplicate CCITT filter test before source edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `1 test files / 871 assertions / 1 failure` because `ccitt_fax_duplicate_filter_declarations_fail_closed` was missing from renderer notes.
- Focused run after source edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php` => `1 test files / 872 assertions / 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-duplicate-filter-boundary-currentbase.php` emits `duplicate_filter_declarations_rejected=true`, `filter_operand_policy=reject_duplicate_filter_declarations`, `filters_resolved=false`, `review_filters=["FlateDecode","CCITTFaxDecode"]`, `preview_only_filters=["CCITTFaxDecode"]`, `ccitt_fax_filter_review_only=true`, `renderer_ccitt_duplicate_filter_note_present=true`, `payload_excluded_from_paragraphs=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF stream dictionary parser, image filter boundary review, Flate prefix metadata, and CCITT Fax review-only handoff. It does not execute Python, models, pypdfium, PIL, external PDF tools, live OCR, or GPU/model workers.

## Non-Overlap

This does not repeat accepted terminal CCITT exclusion, malformed/duplicate CCITT DecodeParms handling, null-filter DecodeParms alignment, preview-only filters before CCITT, post-CCITT filters, RunLength/Flate/LZW/ASCII85/Crypt prefix ownership, direct EOFB/RTC/row ownership, DCT duplicate filter coverage, CMap duplicate filter coverage, encrypted duplicate filter handling, or attachment stream-filter stack work. It only covers duplicate top-level `/Filter` declarations on CCITT Fax image XObjects and the renderer note needed to distinguish that fail-closed boundary.
