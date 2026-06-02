# markerPDF parser xref offset owner boundary

Micro-slice: `parser-xref-offset-owner-boundary-currentbase-20260602T1457Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes low-level PDF text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates structured page text to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` uses pypdfium page text extraction. Source: <https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

That makes xref traversal and object ownership a parser/dependency boundary for the native PHP lane before WordPress paragraphs are emitted. PDF xref table rows and `startxref` offsets are byte offsets to xref/object boundaries, not to PDF-looking text inside another indirect object's stream payload. PDF source: <https://opensource.adobe.com/dc-acrobat-sdk-docs/standards/pdfstandards/pdf/PDF32000_2008.pdf>

## Behavior

`PdfTextExtractor` now rejects xref table sections whose requested byte offset is owned by a direct object body. The exact-offset xref-stream path remains unchanged because xref streams are direct objects and still require the offset to match the `/Type /XRef` object's header.

The focused fixture builds a malformed current-base PDF where:

- the final `startxref` points to `xref ... trailer` text embedded inside object `8 0`'s stream payload;
- that stream-owned fake xref table selects a stale catalog/page tree and stale content stream;
- a real current xref table follows the fake payload and selects the current page tree.

Before the parser guard, WordPress text extraction emitted `Stale owner xref page` and `Stream-owned xref leak`. After the guard, native extraction falls back to the real current xref table and emits only `Current owner boundary page` and `Xref offset owner kept`.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php
FAIL rejects stream-owned startxref table offsets before current-base WordPress text extraction
Expected: array (
  0 => 'Current owner boundary page',
  1 => 'Xref offset owner kept',
)
Actual: array (
  0 => 'Stale owner xref page',
  1 => 'Stream-owned xref leak',
)
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php
1 test files, 10 assertions, 0 failures
```

Adjacent parser/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php
12 test files, 116 assertions, 0 failures
```

Central text extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php
2 test files, 574 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-xref-offset-owner-boundary-currentbase.php
uses_current_owner_boundary_page=true
keeps_xref_offset_owner=true
excluded_stream_owned_xref_page=true
excluded_stream_owned_xref_text=true
excluded_raw_xref_payload=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required isolated-lane checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-parser-xref-offset-owner-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-parser-xref-offset-owner-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok

git diff --check -- lanes/markerpdf
passed
```

Status delta:

- Behavior tests: `522 -> 523`.
- Mapped markerPDF/PDF parser semantics: `370 -> 371 / 78`.
- Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted current xref stream selection, xref-stream `/Prev` exact-offset generation repair, invalid explicit type-1 xref-stream offset rejection, duplicate `/Index` row preservation, hybrid `/XRefStm` direct/free-row precedence, object-stream selected-member recovery, direct object-stream base preservation, object-stream nested-token parsing, top-level stream dictionary parsing, fallback stream object filtering, latest trailer `/Root` generation recovery, or linearized hint-table exclusion.

The new behavior is specifically an xref-table offset owner boundary: plain `xref` table bytes embedded inside another direct object's body are rejected when reached through `startxref` or fallback table scanning.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table/stream parser, direct-object owner range tracking, page-tree walker, stream decoder, content-token text extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
