# markerPDF xref-stream Prev Index/W repair current-base

Slice: `xref-stream-prev-index-width-repair-currentbase`
Session: `port-dev-markerpdf-xref75-20260602T223633Z`
Base accepted HEAD: `ba26c84773f1060ee6d968d946c818afcf0a3c26`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text extraction through `marker/pdf/extract_text.py` into `pdftext.extraction.dictionary_output(...)`, with `naive_get_text()` using pypdfium page text extraction. That makes PDF xref-stream row decoding, `/Prev` incremental chains, and selected direct-object byte offsets native parser dependency boundaries for this PHP lane.

Relevant PDF parser behavior: xref-stream `/W` fields may omit the type and generation fields, defaulting type to in-use and generation to zero, while `/Index` maps decoded rows to object-number ranges. Real damaged PDFs can combine a valid latest `/Prev` chain with malformed sparse `/Index` object numbers; when a type-1 row has an exact direct-object byte offset, tolerant current-base parsing can repair the row owner from the direct object header before stale previous rows merge.

## Behavior

`PdfTextExtractor::xrefStreamEntriesFromDefinition()` now preserves the exact direct-object header generation when it repairs a type-1 xref-stream row by byte-offset ownership. This keeps the existing object-number repair while making zero-width `/W` generation rows carry the current direct object identity selected by the offset.

The focused fixture builds a stale previous xref stream selecting generation-zero catalog/page/content text, then appends current same-number replacement objects. The latest xref stream has `/Prev`, malformed `/Index [30 2 42 2]`, `/W [0 4 0]`, and decoded rows containing only current direct-object offsets. Before offset-owner repair, those rows are assigned to nonexistent object numbers and stale `/Prev` text wins. After repair, WordPress extraction emits only the current page text.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs malformed Prev xref-stream Index rows with zero-width W fields by current offsets

1 test files, 8 assertions, 0 failures
```

Adjacent xref repair gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamGenerationIndexRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridGenerationRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 149 assertions, 0 failures
```

Text/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 675 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-index-width-repair-currentbase.php
uses_current_index_width_repair_page=true
repairs_current_offset_owners=true
excluded_stale_index_width_repair_page=true
page_count=1
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-prev-index-width-repair-currentbase.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted duplicate current-section `/Index` first-row preservation, malformed sparse `/Index` repair with explicit type/generation fields, xref-stream `/Prev` exact-offset generation repair, invalid explicit-offset rejection, `/Size` underdeclaration repair, zero-width type-2 object-stream member-index recovery, hybrid `/XRefStm` direct/free precedence, current object-stream carrier preservation, or stream-owned fake xref rejection.

The bounded behavior is specifically the combined `/Prev` plus malformed sparse `/Index` plus `/W [0 4 0]` current-base repair where exact direct-object offsets select current objects before stale previous rows.

## Dependency Closure

No new support component is needed. This reuses the native direct-object scanner, xref-stream decoder, stream decoder, `/Prev` chain merger, page-tree walker, content-token extractor, and WordPress smoke path. Full upstream runner parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
