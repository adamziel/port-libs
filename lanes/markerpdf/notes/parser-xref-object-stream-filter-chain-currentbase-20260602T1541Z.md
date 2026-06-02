# markerPDF parser xref object-stream filter-chain current-base

Micro-slice: `parser-xref-object-stream-filter-chain-currentbase-20260602T1541Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()`, delegating low-level PDF parsing to `pdftext.extraction.dictionary_output(...)`, and through `naive_get_text()` to pypdfium page text. That makes xref traversal, object-stream expansion, stream filter chains, and DecodeParms resolution dependency behavior for the native PHP parser before WordPress paragraphs are emitted.

PDF object streams can carry ordinary indirect objects, and stream dictionaries can use indirect `/Filter`, `/DecodeParms`, `/N`, `/First`, and `/Length` operands. A current xref stream can therefore select page-tree objects in one object stream while that stream's filter-chain operands are ordinary compressed helper objects recovered from another object stream.

## Behavior

`PdfTextExtractor` now performs bounded iterative object-stream expansion. Each pass decodes currently available `/ObjStm` bodies and adds newly recovered compressed objects; later passes can use those helper objects to decode selected object streams that previously failed because their indirect filter-chain operands were not available yet. The loop is capped at eight passes and never overwrites existing direct/current objects.

The focused fixture builds:

- object stream `6 0`, selected by the current xref stream for catalog, pages, page, and font objects;
- `/Filter 30 0 R` and `/DecodeParms 31 0 R` on object stream `6 0`;
- object stream `7 0`, selected by xref rows for helper objects `30` and `31`;
- a direct current content stream plus an orphan stale fallback stream that must not leak once the current page tree is recovered.

Before the source repair, the parser recovered only the helper objects and did not revisit object stream `6 0`, so the stale fallback stream leaked into text extraction. After the repair, the second expansion pass decodes object stream `6 0`, recovers the current page tree, and blocks fallback scanning.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php
FAIL recovers xref-selected object streams whose filter chain operands are compressed helpers
Actual:
  0 => 'Current chained object stream page',
  1 => 'Compressed filter operands recovered',
  2 => 'Stale chained fallback leak',
1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php
1 test files, 10 assertions, 0 failures
```

Adjacent parser/xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php
13 test files, 696 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-filter-chain-currentbase.php
uses_current_chained_object_stream_page=true
recovers_compressed_filter_operands=true
excludes_stale_fallback_stream=true
excludes_filter_operand_text=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-filter-chain-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

Status delta: behavior tests `530 -> 531`; mapped parser semantics `377 -> 378 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, standalone filter-chain DecodeParms recovery, indirect filter-name arrays, stream-filter fail-closed boundaries, xref stream type-2 object-stream base preservation, omitted member-index repair, object-stream nested-token parsing, xref offset owner rejection, or hybrid xref generation/free-entry precedence.

The new behavior is specifically cross-object-stream dependency recovery for current xref-selected object streams whose filter-chain operands are compressed helper objects recovered in an earlier object-stream expansion pass.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref stream parser, object-stream decoder, stream filter-chain dispatcher, DecodeParms predictor decoder, page-tree walker, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
