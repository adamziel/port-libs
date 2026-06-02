# markerPDF xref object-stream free-entry Prev review

Micro-slice: `xref-object-stream-free-entry-prev-review-currentbase-20260602T171314Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes structured PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and delegates low-level parser selection to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates page text extraction to pypdfium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

For PDF 1.5 xref streams, type-0 rows are free entries. In an incremental `/Prev` chain, the latest section remains authoritative for an object number, so a current free row for a previously compressed page object must suppress the stale previous type-2 object-stream member before WordPress paragraphs are produced.

## Behavior

The current native parser already honors this boundary. This handoff adds focused regression coverage and a WordPress smoke for an incremental PDF where:

- the previous xref stream maps page object `4` as member `0` of object stream `6`;
- the latest xref stream points `/Prev` at that old section but marks object `4` free with generation `1`;
- the latest page tree references a current direct page and the now-free object `4`.

`PdfTextExtractor` emits only:

- `Current free Prev review page`
- `Compressed Prev member suppressed`

The stale previous object-stream page is excluded from `extractTextLines()`, `extractTextRuns()`, `extractPlainText()`, `naiveGetText()`, page count metadata, and the WordPress smoke output without running Python, pdftext, pypdfium, models, or external PDF tools.

## Evidence

Current-base probe before editing:

```text
array (
  0 => 'Current free Prev review page',
  1 => 'Compressed Prev member suppressed',
)
array (
  'pdf_toc' =>
  array (
  ),
  'document_info' =>
  array (
  ),
  'pages' => 1,
)
```

Focused gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps current xref-stream free row before stale Prev object-stream member

1 test files, 8 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefIncrementalFreeEntryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 81 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-free-entry-prev-currentbase.php
uses_current_free_prev_page=true
suppresses_stale_prev_object_stream_member=true
keeps_current_free_row_authoritative=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

## Non-Overlap

This does not repeat accepted hybrid table free-entry precedence over companion `/XRefStm`, latest xref-stream free rows suppressing stale direct `/Prev` page/content objects, previous type-2 rows whose carriers were never selected, current object-stream owner replacement over stale `/Prev` hybrid rows, xref-stream `/Prev` exact-offset generation repair, type-2 object-stream base preservation, omitted member-index repair, or unselected object-stream fallback suppression.

The new behavior is specifically a latest xref-stream type-0 free row for an object number that the previous xref section advertised as a type-2 compressed object-stream member.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref stream parser, `/Prev` chain merger, object-stream expander, page-tree walker, stream decoder, and content-token extractor. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
