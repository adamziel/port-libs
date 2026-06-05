# markerPDF Named Destinations Indirect Page Kids Boundary

Micro-slice: `markerpdf-named-destinations-boundary-current-base-20260605T191813Z`
Session: `port-dev-markerpdf-named-destinations-20260605T191813Z`
Base accepted HEAD: `b3de8afc1ef1abca247016bc3fd047a029ddfa72`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF relies on parser/PDFium/pdftext page order before OCR/model stages. Under the current no-GPU scope, this lane maps that boundary in native PHP for searchable PDFs and WordPress review metadata.
- PDF page-tree `/Kids` values are PDF objects. A `/Pages` node may point `/Kids` at an indirect array object; named-destination page indexes must use that array order and must not fall back to detached `/Page` objects outside the page tree.
- This slice stays inside the no-GPU markerPDF scope: no OCR, Surya, Texify, Torch, pypdfium/PDFium execution, Poppler, Ghostscript, Python, model workers, raster rendering, or external PDF tools were run.

## Implementation

- `PdfNamedDestinationExtractor::collectPageObjectIds()` now resolves the page-tree `/Kids` value before extracting child page references.
- Added a fixture where `/Pages /Kids 12 0 R` resolves to `[4 0 R 3 0 R]`, reversing object-number order, plus a detached `/Page` object that has a stale named-destination row.
- Added a WordPress smoke that renders native named-destination metadata while proving the detached page destination stays out of document destination review and visible imported text.

## Evidence

Red-first focused run before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectPageKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses indirect page-tree Kids arrays for named-destination page order before fallback scanning (lanes/markerpdf/tests/PdfNamedDestinationIndirectPageKidsBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'First Logical',
  1 => 'Second Logical',
  2 => 'LegacySecond',
)
Actual: array (
  0 => 'First Logical',
  1 => 'Second Logical',
  2 => 'Detached Decoy',
  3 => 'LegacySecond',
)
FAIL keeps detached page objects out of named-destination metadata and visible WordPress text (lanes/markerpdf/tests/PdfNamedDestinationIndirectPageKidsBoundaryCurrentBaseTest.php)
Condition is not true

1 test files, 7 assertions, 2 failures
```

Focused gate after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestinationIndirectPageKidsBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses indirect page-tree Kids arrays for named-destination page order before fallback scanning
PASS keeps detached page objects out of named-destination metadata and visible WordPress text

1 test files, 17 assertions, 0 failures
```

Adjacent named-destination family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfNamedDestination*.php
Focused test run: 28 selected test files (root lock skipped)
28 test files, 780 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-named-destination-indirect-page-kids-currentbase.php
```

The smoke emits `destination_names=["First Logical","Second Logical","LegacySecond"]`, `document_destination_count=3`, `document_destination_page_count=2`, `indirect_page_kids_order_resolved=true`, `detached_page_destination_excluded=true`, `visible_text_excludes_destination_metadata=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- Focused named-destination assertions: new file at `17` assertions.
- New focused PASS cases: `+2`.
- `phpPass`: `2167 -> 2169`.
- `wordpressScenarios`: `1867 -> 1868`.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted named-destination name-tree extraction, legacy `/Dests`, duplicate key precedence, byte-wise `/Limits`, invalid destination name-tree `/Kids`, indirect destination name-tree arrays, exact object generations, action dictionaries, page-only destinations, invalid page operands, view-mode validation, xref-offset selection, xref-stream `/Prev`, object-stream member expansion, parser stream dictionary escape handling, trailer-root selection, outline destination action context, link annotation name-tree `/Limits`, PageLabels, attachment/page-tree indirect Kids handling, page-resource inheritance, fonts/CMaps, image filters, encrypted preflight, or supplied table/equation behavior. The bounded behavior is only the standalone named-destination extractor using indirect page-tree `/Kids` array order before fallback page scanning.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, tokenizer, exact-generation resolver, page-tree walker, named-destination normalizer, metadata extractor, text extractor, and WordPress smoke path. Full upstream OCR/model/PDFium parity remains intentionally out of no-GPU scope because it depends on pdftext/PDFium, Surya/OCR/layout models, Texify/Torch, rendering/image model paths, and Streamlit/FastAPI runtime workers.
