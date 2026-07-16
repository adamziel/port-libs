# markerpdf font-width advance CID absolute Tm styled gap current base

Date: 2026-06-05 UTC

Slice: `markerpdf-font-width-advance-boundary-current-base-20260605T171200Z`

Accepted base: `653192ad10f457ea19611d2f9d5658960027a3aa`

## Source truth

Upstream markerPDF carries searchable PDF text spans from `pdftext.extraction.dictionary_output(..., keep_chars=false, ...)` through native span dictionaries with text and bbox geometry. That geometry is not simple-font-only: horizontal Type0/CIDFont text with reliable CID width evidence must preserve visible same-line positioned gaps in styled bboxes, while vertical writing mode, source-width fallback, and CID range remap cases remain separate boundaries.

This slice stays inside the no-GPU markerPDF scope. It does not run OCR, Surya, Texify, Torch, model workers, Python markerPDF, or external PDF tools.

## Red-first evidence

Added `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` coverage for a horizontal Type0 `/Identity-H` CIDFont with `/W [1 4 1000]`, `/DW 1000`, and a ToUnicode map for CIDs 1-4. The searchable text already extracted as:

- `AB CD`
- `ABCD`

Before the source edit, the first styled line compacted the second span bbox:

- expected `[[0,0,24,12],[36,0,60,12]]`
- actual `[[0,0,24,12],[24,0,48,12]]`

Command:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result before source edit: `1 test files, 400 assertions, 1 failures`.

## Implementation

`PdfTextExtractor::styledTextMatrixGapWidth()` now delegates its font-map eligibility to a bounded helper. Simple fonts retain the existing path. Horizontal Type0/CID maps can preserve absolute `Tm` styled gaps only when:

- writing mode is horizontal;
- the ToUnicode source key width lines up with the CID encoding code-space width;
- the map is not a CID range-remap fallback;
- CID `/W`, `/DW`, or CID set width evidence is present.

This deliberately preserves existing compact styled bboxes for CMap source-width fallback and high-CID range-remap tests.

## Verification

Focused changed test:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`

Result after source edit: `1 test files, 409 assertions, 0 failures`.

Adjacent CID/CMap width family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`

Result: `5 test files, 737 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php > /tmp/markerpdf-font-width-advance-boundary-currentbase.html`

Result: exit code 0. The smoke JSON includes `cid_absolute_tm_styled_gap_lines_preserved=true`, `cid_absolute_tm_styled_gap_first_bboxes_preserved=true`, `cid_absolute_tm_styled_gap_second_bboxes_preserved=true`, `cid_absolute_tm_styled_gap_compaction_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

PHP lint:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php`

Result: no syntax errors.

Lane JSON check:

`php -r '$files=["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"]; foreach ($files as $file) { json_decode((string) file_get_contents($file), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, $file . ": " . json_last_error_msg() . PHP_EOL); exit(1); } echo $file . ": valid JSON\n"; }'`

Result: both lane JSON files valid.

`git diff --check -- lanes/markerpdf`

Result: passed.

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. The patch reuses the existing native PHP font map, CID width, ToUnicode/CMap source boundary, and styled-span bbox machinery.

## Non-overlap

Avoided the accepted xref object-stream explicit-zero carrier slice, simple-font absolute `Tm` gap slice, font descriptor generation slice, vertical `/W2` CID height slice, Type3 FontMatrix width slice, and CMap source-width fallback slices. This patch only adds direct horizontal Type0/CID absolute `Tm` styled gap preservation where CID width evidence and direct source keys make bbox geometry stable.
