# markerPDF xref-stream Prev Index/W current-base boundary

Slice: `xref-stream-prev-index-width-currentbase-20260602T1357Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF parsing and text extraction through `marker/pdf/extract_text.py`, where `get_text_blocks()` calls `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` uses `pypdfium2` page text extraction. That makes xref-stream traversal, `/Prev` incremental sections, and selected object-byte offsets a parser/dependency boundary for the native PHP lane.

The PDF 1.7 xref-stream rules define `/Index` as sorted, non-overlapping object-number subsections with at most one entry for an object in a section; `/W` field widths can omit the type field, defaulting it to type 1, and `/Prev` chains previous xref streams. This slice maps the fail-closed boundary for malformed duplicate current-section `/Index` rows while preserving the first current row.

## Behavior

`PdfTextExtractor::xrefStreamEntriesFromDefinition()` now keeps the first parsed entry for an object number inside a single decoded xref stream. Later overlapping `/Index` rows in the same stream are ignored instead of overwriting the selected current row.

The focused fixture builds a previous xref stream with stale generation-0 page content, then appends generation-1 current catalog/page/content objects. The current xref stream has `/Prev`, sparse `/Index`, and `/W [0 4 0]`; its first object-5 row points at current content and a later malformed duplicate object-5 row points back to the stale previous offset. Before the fix, WordPress text extraction emitted the stale page. After the fix, it emits only the current page text.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
FAIL keeps first current xref stream Index row before duplicate stale Prev row
Actual: array (
  0 => 'Stale duplicate index width page',
)
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
1 test files, 8 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php
5 test files, 48 assertions, 0 failures
```

Central text extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
2 test files, 567 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-prev-index-width-boundary.php
uses_current_duplicate_free_page=true
keeps_first_current_index_width_row=true
excluded_stale_duplicate_index_width_page=true
page_count=1
```

Changed PHP lint passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-prev-index-width-boundary.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted simple xref-stream `/Index` plus zero-width `/W` default decoding, xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, object-stream omitted member-index repair, hybrid `/XRefStm` direct/free-row precedence, latest trailer `/Root` generation recovery, or unselected object-stream trailer-boundary repair. The new behavior is specifically duplicate current-section `/Index` rows in an xref stream with `/Prev` and zero-width `/W` fields.

## Dependency Closure

No new support component is needed. The slice reuses the native direct-object scanner, xref table/stream parser, `/Prev` chain merger, stream decoder, page-tree walker, and content-token extractor. Full upstream Python/model/benchmark parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled, Texify, Streamlit/FastAPI runtime paths, and benchmark/model download tooling.
