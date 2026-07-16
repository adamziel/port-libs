# markerPDF xref incremental free-entry generation

Slice: `xref-incremental-free-entry-generation-currentbase-20260602T1608Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes structured page text through `pdftext.extraction.dictionary_output(...)` and fallback text through pypdfium page text in `marker/pdf/extract_text.py`: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

That keeps xref traversal, incremental `/Prev` chains, generation reuse, and parser-selected page objects in the native PHP parser boundary before WordPress import. A latest xref-stream type-0 free row is authoritative for that object number, including its generation field, so stale previous page/content objects must not be revived from `/Prev` after an incremental update frees them.

## Behavior

The current-base native parser already honors this boundary. This handoff adds focused regression coverage and a WordPress smoke for an incremental PDF whose previous xref table contains stale live page and content objects, while the latest xref stream marks those object numbers free with generation `1` and points `/Root` at a replacement page tree.

`PdfTextExtractor` emits only:

- `Current incremental free page`
- `Free generation row kept`

The stale previous page and stale previous content stream are excluded from `extractTextLines()`, `extractTextRuns()`, `extractPlainText()`, `naiveGetText()`, page count metadata, and the WordPress example output.

## Evidence

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps current incremental xref-stream free generation rows before stale Prev objects

1 test files, 9 assertions, 0 failures
```

Adjacent xref/object-stream parser gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php
10 test files, 95 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-incremental-free-entry-generation-currentbase.php
uses_current_incremental_page=true
keeps_free_generation_row=true
excluded_stale_prev_page=true
excluded_stale_prev_content_stream=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required checks:

```text
php -l lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-xref-incremental-free-entry-generation-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-xref-incremental-free-entry-generation-currentbase.php

jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
passed

git diff --check -- lanes/markerpdf
passed
```

Behavior-test counter moves `534 -> 535`; mapped semantic counter moves `381 -> 382 / 78`.

## Non-Overlap

This does not repeat accepted object-generation free-entry reuse guards in a single current xref table, hybrid table free-entry precedence over companion `/XRefStm`, current hybrid direct-generation precedence, xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, duplicate `/Index` row preservation, unselected object-stream suppression, type-2 omitted member-index repair, object-stream filter-chain recovery, xref offset-owner rejection, or fake xref-stream owner rejection.

The new coverage is specifically the incremental `/Prev` chain boundary where the latest xref stream itself marks previous direct page/content object numbers free with a higher generation.

## Dependency Closure

No new support component is needed. The slice reuses native PHP direct-object scanning, xref table/stream parsing, `/Prev` chain merging, free-entry handling, page-tree traversal, stream decoding, and content-token extraction. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, live benchmark tooling, and model/download setup.

## Next Task

Continue with non-overlapping markerPDF parser/import fidelity gaps: xref/object stream recovery, page/resource inheritance, font/CMap widths, annotation/rich-media review, AcroForm/security metadata, image/color-space planning, and supplied-document table/OCR boundaries with focused PHP evidence.
