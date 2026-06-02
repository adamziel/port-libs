# markerPDF xref incremental object-stream free repair

Micro-slice: `xref-incremental-object-stream-free-repair-currentbase-20260602T1619Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native PDF text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates low-level parsing to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` uses pypdfium page text extraction. The PHP lane therefore owns the parser/dependency boundary for xref traversal, incremental `/Prev` chains, object-stream carrier ownership, and visible WordPress paragraph text before any Python models or external PDF tools run.

For PDF object streams, a type-2 xref row identifies both the object-stream carrier and the compressed member index. A previous incremental section's type-2 row is only safe to replay when the previous chain also selected the carrier storage. Otherwise a later unlisted replacement `/ObjStm` with the same object number can be incorrectly treated as the old carrier.

## Behavior

`PdfTextExtractor::previousCompressedEntryUsesUpdatedObjectStream()` now skips previous type-2 rows when the previous xref chain never selected their object-stream carrier. This keeps valid current no-xref rebuild fallback and current xref-selected object-stream fixtures intact, but prevents an old compressed page row from binding to a newer unlisted replacement object stream during an incremental `/Prev` merge.

The focused fixture builds:

- a previous xref stream with object `4` as a type-2 compressed page in object stream `6`, but with no selected xref row for carrier object `6`;
- a current incremental xref stream with `/Prev` that selects the current catalog/pages/page/content but intentionally does not select replacement object stream `6`;
- a replacement `6 0 obj /Type /ObjStm` whose compressed member and referenced stream contain decoy page text.

Before the fix, the previous type-2 row expanded through the unlisted replacement object stream and emitted `Unlisted replacement object stream leak`. After the fix, WordPress extraction emits only `Current incremental guard page` and `Unlisted carrier ignored`.

## Evidence

Red probe before the source repair:

```text
array (
  0 => 'Current incremental guard page',
  1 => 'Unlisted replacement object stream leak',
)
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS skips previous type-2 rows whose object-stream carrier was never selected before incremental free repair

1 test files, 10 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 99 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-incremental-object-stream-free-repair-currentbase.php
uses_current_incremental_guard_page=true
skips_unselected_previous_type2_row=true
ignores_unlisted_replacement_object_stream=true
excludes_replacement_member_text=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Changed-file lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-incremental-object-stream-free-repair-currentbase.php
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted hybrid xref table free/direct precedence, object-generation free-entry reuse guards, xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, duplicate `/Index` row preservation, xref-stream type-2 object-stream base preservation, omitted member-index repair, unselected object-stream trailer-boundary suppression, stream-owned fake xref object rejection, or indirect object-stream filter-chain operand recovery.

The bounded behavior is specifically a previous `/Prev` type-2 compressed-object row whose previous xref chain never selected the object-stream carrier, preventing that row from binding to a newer unlisted replacement `/ObjStm`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` merger, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
