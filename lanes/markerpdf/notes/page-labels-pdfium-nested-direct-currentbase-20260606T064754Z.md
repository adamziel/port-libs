# PageLabels PDFium nested direct tree current-base

## Scope

- Lane: markerpdf
- Micro-slice: markerpdf-page-labels-boundary-current-base-20260606T064754Z
- Base accepted HEAD: 16abed1ad9fae5cddf75eba6926644d7427f2294

This slice adds focused native PHP coverage for PDFium-style nested direct
`/PageLabels` number-tree `/Kids` dictionaries. The fixture preserves nested
`/Limits`, upper/lower Roman labels, and repeated-letter alphabetic labels
before MarkerAppPreview and WordPress page-break metadata are emitted.

## Source Truth

- Upstream markerPDF extracts searchable PDF text page-by-page through the
  pdftext/PDFium path before model handoff:
  https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- PDFium page-label unit coverage includes nested direct `/Kids` number-tree
  dictionaries and repeated-letter alphabetic page labels:
  https://pdfium.googlesource.com/pdfium.git/+/refs/heads/chromium/7430/core/fpdfdoc/cpdf_pagelabel_unittest.cpp

## Implementation

- Added `PdfPageLabelsPdfiumNestedDirectTreeCurrentBaseTest.php` with a six-page
  synthetic searchable PDF whose catalog `/PageLabels` points to nested direct
  `/Kids` dictionaries.
- Added `wordpress-pdf-page-labels-pdfium-nested-direct-currentbase.php` to
  prove the same labels flow into Gutenberg page-break metadata and visible
  paragraphs without Python, models, PDFium execution, or external PDF tools.
- Updated markerpdf lane status and manifest evidence for one new current-base
  PageLabels behavior.

## Evidence

- `php -l lanes/markerpdf/tests/PdfPageLabelsPdfiumNestedDirectTreeCurrentBaseTest.php`
  - No syntax errors detected.
- `php -l lanes/markerpdf/examples/wordpress-pdf-page-labels-pdfium-nested-direct-currentbase.php`
  - No syntax errors detected.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabelsPdfiumNestedDirectTreeCurrentBaseTest.php`
  - 1 test files / 13 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfPageLabels*CurrentBaseTest.php`
  - 16 test files / 429 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-page-labels-pdfium-nested-direct-currentbase.php`
  - Emitted `markerpdf-page-labels-pdfium-nested-direct` with labels
    `["I","II","abcZ","abcAA","i","z"]`,
    `nested_direct_kids_preserved=true`,
    `repeated_alphabetic_label_preserved=true`,
    `roman_label_sections_preserved=true`,
    `stale_fallback_labels_excluded=true`,
    `executes_python_or_models=false`, and
    `executes_external_pdf_tools=false`.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'`
  - markerpdf json ok.
- `git diff --check -- lanes/markerpdf`
  - Passed with no output.

## Non-Overlap

This does not repeat the accepted direct kid dictionary, kid-reference relay,
array-object-tail, indirect operand, PDFDocEncoding prefix, duplicate key,
same-lower limit, encrypted preview, or outline page-label propagation slices.
It specifically maps the nested direct PDFium number-tree boundary through the
existing native searchable-PDF parser and preview metadata path.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF object
scanner, PageLabels number-tree parser, label formatter, MarkerAppPreview page
inventory path, and WordPress smoke renderer. OCR, Surya/Texify/Torch, PDFium
runtime execution, and external PDF tools remain intentionally out of scope.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping parser/converter
behavior: fonts, CMaps, stream filters, xref repair, metadata, annotations,
forms, page geometry, image/filter metadata, and supplied-boundary table or
equation handoffs.
