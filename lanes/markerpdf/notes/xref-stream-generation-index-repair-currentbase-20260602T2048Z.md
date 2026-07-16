# markerPDF xref-stream generation Index repair current-base

Slice: `xref-stream-generation-index-repair-currentbase`
Session: `port-dev-markerpdf-xref48-20260602T2048Z`
Base accepted HEAD: `ea6b9c60b46ea0618978b2deaa95900cf2e78648`

## Source Truth

Upstream markerPDF at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates PDF text extraction through `marker/pdf/extract_text.py` into `pdftext`/PDFium-style parsing before model work. That makes xref-stream row ownership, `/Index` ranges, generation fields, and `/Prev` merging native parser dependency boundaries for this PHP lane.

PDF xref-stream `/Index` normally maps decoded rows to object numbers, while type-1 row field 2 gives the byte offset of the direct indirect object and field 3 gives the generation. This slice covers a malformed current xref stream where `/Index` labels current rows as unrelated high object numbers even though their explicit offsets point at current direct object headers. Tolerant current-base import should repair that row owner before merging stale `/Prev` rows.

## Behavior

`PdfTextExtractor::xrefStreamEntriesFromDefinition()` now receives the direct object definitions and, for type-1 rows with explicit offsets, repairs the row object number to the direct object header found at that byte offset. First decoded row for an object still wins, `/Prev` merge precedence remains unchanged, and type-2 object-stream rows keep their existing strict member-index behavior.

The focused fixture builds:

- a previous xref stream selecting generation-zero catalog/page/content with stale text;
- current same-generation replacement catalog/page objects whose page references a generation-one content stream;
- a latest xref stream with `/Prev`, `/W [1 4 1]`, and malformed `/Index [10 4]`;
- current decoded rows whose explicit offsets point at objects `1 0`, `2 0`, `3 0`, and `4 1`.

Before the repair, `/Prev` rows for objects `1` through `4` won and WordPress extraction emitted `Stale misindexed Prev page`. After the repair, the current page emits `Current generation Index repair page` and `Offset owner row repaired`.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamGenerationIndexRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL repairs malformed xref-stream Index object numbers by current generation offsets before Prev rows
Expected: array (
  0 => 'Current generation Index repair page',
  1 => 'Offset owner row repaired',
)
Actual: array (
  0 => 'Stale misindexed Prev page',
)
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamGenerationIndexRepairCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS repairs malformed xref-stream Index object numbers by current generation offsets before Prev rows
1 test files, 8 assertions, 0 failures
```

Adjacent xref-stream/object-stream generation gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamGenerationIndexRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevGenerationIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamGenerationPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefCurrentBaseRepairBoundaryTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 168 assertions, 0 failures
```

Broader focused xref/object-stream family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXref*.php lanes/markerpdf/tests/PdfParserXref*.php lanes/markerpdf/tests/PdfObjectStream*.php
Focused test run: 35 selected test files (root lock skipped)
35 test files, 442 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-generation-index-repair-currentbase.php
uses_current_generation_index_repair_page=true
repairs_offset_owner_row=true
excluded_stale_misindexed_prev_page=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Changed PHP lint:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamGenerationIndexRepairCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-generation-index-repair-currentbase.php
```

Whitespace check:

```text
git diff --check -- lanes/markerpdf
passed
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted duplicate current `/Index` first-row preservation, `/Prev` same-offset object-stream carrier generation noise, zero-width object-stream member-index recovery, duplicate zero-width member rejection, hybrid table direct-generation precedence, xref-stream `/Size` underdeclaration repair, invalid explicit-offset generation repair, current object-stream base preservation, free-entry suppression, or stream-owned fake xref rejection.

The bounded behavior is specifically current xref-stream type-1 row object-number repair when malformed sparse `/Index` object numbers conflict with exact direct-object offsets.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, xref-stream decoder, `/Prev` chain merger, page-tree walker, stream decoder, and WordPress smoke path. Full markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
