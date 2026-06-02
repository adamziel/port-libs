# markerPDF xref object-stream type-2 index current-base boundary

Slice: `xref-object-stream-type2-index-currentbase-20260602T1424Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes low-level PDF text extraction through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates to `pdftext.extraction.dictionary_output(...)` and `naive_get_text()` uses pypdfium page text extraction. That makes PDF parser object resolution a dependency boundary for the PHP lane before WordPress paragraphs are emitted.

PDF xref-stream type-2 entries identify the containing object stream and the member index inside that stream. PDFium mirrors this by storing `archive_obj_num` and `archive_obj_index` for compressed entries, then parsing the requested object from that indexed object-stream member. PDFium also refuses to add known object streams as compressed members of other object streams. This PHP slice maps that same fail-closed boundary for malformed xref streams without running Python, pdftext, pypdfium, or external PDF tools.

## Behavior

`PdfTextExtractor` now preserves a scanned direct `/Type /ObjStm` definition when a malformed type-2 xref-stream row tries to classify that object-stream base object as compressed. Selected compressed members still require a matching type-2 row and explicit member indexes remain strict.

The focused fixture builds:

- a current direct object stream `6 0` with object `4` at explicit member index `1`;
- a page tree that references compressed page object `4 0`;
- a malformed xref-stream row that advertises `6 0` itself as type-2 in object stream `7`;
- an orphan direct text stream that must not leak through fallback scanning once the compressed page object is recovered.

Before the fix, the page object was unavailable and the orphan stream leaked into WordPress text extraction. After the fix, the direct object-stream base is preserved, object `4` is expanded from index `1`, and only the current page content is emitted.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php
FAIL keeps current object-stream base direct while applying explicit type-2 member index
Actual:
  0 => 'Current type-2 index page',
  1 => 'Object stream base preserved',
  2 => 'Stale orphan fallback stream',
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php
1 test files, 10 assertions, 0 failures
```

Adjacent xref/object-stream gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
5 test files, 43 assertions, 0 failures
```

Combined focused xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
6 test files, 53 assertions, 0 failures
```

Central text extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php
2 test files, 574 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-type2-index-currentbase.php
uses_current_type2_index_page=true
preserves_direct_object_stream_base=true
excluded_decoy_first_member=true
excluded_malformed_compressed_base=true
excluded_orphan_fallback_stream=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

Required local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-type2-index-currentbase.php
php -r 'json_decode(...)' lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Status delta: behavior tests `518 -> 519`; mapped upstream semantics `366 -> 367 / 78`.

## Non-Overlap

This does not repeat accepted omitted type-2 member-index repair, hybrid xref table direct-generation precedence, hybrid free-entry conflict precedence, unselected object-stream trailer-boundary suppression, object-generation free-entry reuse guards, xref-stream `/Prev` exact-offset generation repair, invalid explicit xref-stream offset rejection, duplicate `/Index` row preservation, object-stream nested-token parsing, or object-stream indirect `/Length`/`/Filter`/`/N`/`/First` recovery.

The new behavior is specifically preserving the current direct `/ObjStm` base object when a malformed current xref-stream type-2 row tries to hide that base, while still applying the explicit type-2 index for the actual compressed page object.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, xref stream parser, object-stream decoder, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
