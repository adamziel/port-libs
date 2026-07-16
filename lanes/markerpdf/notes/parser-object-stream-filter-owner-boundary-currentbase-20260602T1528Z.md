# markerPDF parser object-stream filter owner boundary

Micro-slice: `parser-object-stream-filter-owner-boundary-currentbase-20260602T1528Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes low-level PDF text extraction through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates structured page text to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` uses pypdfium page text extraction. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

PDFium treats `/Type /ObjStm` streams as compressed-object containers: it validates `/ObjStm`, loads filtered carrier data, reads the object header table, and parses a requested member object at its indexed offset instead of exposing the entire decoded carrier as page text. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

`PdfTextExtractor::allDecodedStreams()` now excludes live direct `/Type /ObjStm` stream dictionaries from fallback visible stream enumeration. Object streams still decode through `objectsFromObjectStreams()` for selected compressed members; the fallback scanner just stops treating the carrier stream payload itself as a page content stream.

The focused fixture builds a current-base PDF with no page tree, forcing fallback stream extraction:

- object `1 0` is the current xref-selected Flate content stream and must stay visible;
- object `2 0` is a live Flate `/ObjStm` carrier whose decoded member bytes contain `BT ... Tj` text operators and metadata text;
- object `3 0` is a stale Flate stream marked free in the current xref table.

Before the fix, fallback extraction emitted `Filtered object stream carrier leak` from the decoded `/ObjStm` carrier. After the fix, WordPress output keeps only `Current filtered fallback page` and `Object stream carrier excluded`.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php
FAIL keeps filtered object stream carrier payload out of fallback WordPress text extraction
Expected: array (
  0 => 'Current filtered fallback page',
  1 => 'Object stream carrier excluded',
)
Actual: array (
  0 => 'Current filtered fallback page',
  1 => 'Object stream carrier excluded',
  2 => 'Filtered object stream carrier leak',
)
1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php
PASS keeps filtered object stream carrier payload out of fallback WordPress text extraction
1 test files, 10 assertions, 0 failures
```

Adjacent text/parser gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php
9 test files, 648 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-object-stream-filter-owner-boundary-currentbase.php
current_filtered_fallback_visible=true
object_stream_carrier_excluded=true
stale_free_stream_excluded=true
object_stream_member_metadata_excluded=true
page_count=0
executes_python_or_models=false
executes_external_pdf_tools=false
```

Status delta:

- Behavior tests: `528 -> 529`.
- Mapped markerPDF/PDF parser semantics: `375 -> 376 / 78`.
- Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted current xref-selected direct stream fallback, top-level stream dictionary parsing, object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery, object-stream nested token boundaries, unselected type-2 member suppression, direct object-stream base preservation, xref offset-owner boundary rejection, stream-filter fail-closed handling, or image/embedded/PieceInfo stream exclusion.

The new behavior is specifically the fallback owner boundary for filtered `/ObjStm` carrier streams: the carrier remains available for compressed-object member recovery but is not decoded as visible page text.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref table/stream parser, object-stream decoder, stream-filter dispatcher, fallback content-token parser, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
