# markerPDF classic xref free-object map PDF-whitespace boundary

Micro-slice: `markerpdf-classic-xref-rebuild-boundary-current-base-20260607T100813Z`
Session: `port-dev-markerpdf-xref-classic-rebuild-20260607T100813Z`
Accepted base: `9249a8421a3ff1980e89d00422073eb64b55016c`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text through PDF parser output before any OCR/model fallback. Under the current no-GPU lane scope, the native PHP parser owns xref repair, annotation filtering, and WordPress review metadata without Python, PDFium, OCR, Surya, Texify, Torch, or external PDF tools.

PDF classic xref tables allow PDF whitespace between subsection fields. The main text/metadata parser already normalizes NUL and form-feed bytes while rebuilding damaged classic xref tables. `PdfXrefFreeObjectMap` used its own lightweight parser and missed that normalization, so stale freed annotations could still be promoted to WordPress link/review metadata even though current text extraction selected the repaired table.

## Behavior

- `PdfXrefFreeObjectMap::xrefTableRows()` now normalizes NUL and form-feed bytes before parsing classic xref subsection headers and rows.
- The fixture builds a previous section where annotation object `7` is live, then appends a current damaged-startxref classic table whose page/content rows and free row use NUL/form-feed delimiters.
- Current page text remains selected, object `7` is marked free, and `PdfLinkAnnotationExtractor` / `PdfAnnotationExtractor` suppress the stale URI and stale review annotation before WordPress import.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapWhitespaceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL normalizes PDF whitespace in rebuilt classic free-object xref rows before annotation review (lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapWhitespaceBoundaryCurrentBaseTest.php)
PDF whitespace in rebuilt free rows must preserve current free annotations.

1 test files, 4 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapWhitespaceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS normalizes PDF whitespace in rebuilt classic free-object xref rows before annotation review

1 test files, 14 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefClassicRebuildFreeObjectMapCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefClassicPdfWhitespaceBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
PASS accepts PDF NUL whitespace in rebuilt classic xref tables before WordPress imports
PASS rebuilds damaged classic startxref for the free-object map before annotation review
PASS ignores literal-string xref decoy while rebuilding the free-object map before annotation review
PASS ignores name-delimited xref pseudo-table while rebuilding the free-object map before annotation review
PASS uses EOF-bounded current classic xref for the free-object map when final startxref is missing

2 test files, 81 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-classic-rebuild-free-object-map-whitespace-currentbase.php
```

The smoke exits 0 and reports `current_text_selected=true`, `free_object_map_rebuilt_to_current_pdf_whitespace_xref=true`, `suppresses_stale_link_annotation=true`, `suppresses_stale_review_annotation=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, classic xref rebuild path, free-object map, annotation/link extractors, and WordPress smoke path. No OCR/model/PDF rendering dependency is introduced.

## Non-Overlap

This does not repeat accepted text/metadata PDF-whitespace xref parsing, damaged classic startxref selection, name-delimited xref rejection, literal/composite decoys, missing-final-startxref EOF repair, xref-stream repair, object-stream repair, or main text extraction behavior. The bounded change is only the standalone free-object map row parser used by annotation/link filtering.
