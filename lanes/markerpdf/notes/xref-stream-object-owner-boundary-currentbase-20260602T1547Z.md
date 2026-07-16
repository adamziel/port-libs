# markerPDF xref-stream object owner boundary

Micro-slice: `xref-stream-object-owner-boundary-currentbase-20260602T1547Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` reaches page text through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates structured extraction to `pdftext.extraction.dictionary_output(...)`, while `naive_get_text()` delegates page text extraction to pypdfium. That makes PDF parser object ownership a required native PHP boundary before WordPress paragraphs are emitted.

PDF parser behavior for this slice: a cross-reference stream is an indirect stream object selected by `startxref`; object headers inside another stream's declared payload are not file-level indirect objects and cannot own `startxref`. PDFium-style object parsing uses xref offsets to load real indirect objects and treats stream bytes as payload, so embedded `endstream`, `endobj`, and `obj` tokens inside the declared stream length must not promote a fake xref stream.

## Behavior

`PdfTextExtractor::pdfObjectEndOffset()` now honors a direct top-level stream `/Length` while scanning direct object boundaries. If the declared stream payload contains early `endstream/endobj` bytes followed by a fake `20 0 obj << /Type /XRef ... >>`, the scanner skips through the declared payload to the real stream terminator before accepting the next file-level object.

The focused fixture builds:

- a current page tree and current xref stream object;
- a stale page tree targeted by a fake xref stream object;
- a stream payload whose declared `/Length` contains an early `endstream/endobj` pair plus the fake xref stream object bytes;
- a latest `startxref` offset pointing at that embedded fake xref stream object.

Before the fix, the fake embedded xref stream became a parsed indirect object and redirected extraction to the stale page tree. After the fix, the fake object remains stream payload, current xref-stream entries win, stale page text is excluded, and the carrier payload is not emitted as visible WordPress text.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php
FAIL rejects stream-owned xref stream objects before current-base WordPress text extraction
Actual:
  0 => 'Stale embedded xref stream page',
  1 => 'Stream-owned xref object leak',
1 test files, 1 assertions, 1 failures
```

Focused green after repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php
1 test files, 10 assertions, 0 failures
```

Adjacent parser/xref owner gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php
6 test files, 61 assertions, 0 failures
```

Final parser/text/xref gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefGenerationRepairBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php
14 test files, 706 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-object-owner-boundary-currentbase.php
uses_current_xref_stream_page=true
current_base_xref_stream_wins=true
embedded_xref_stream_rejected=true
stream_owned_xref_payload_excluded=true
owner_carrier_payload_excluded=true
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness status: not run - isolated micro-slice.

Status delta: behavior tests `531 -> 532`; mapped markerPDF/PDF parser semantics `378 -> 379 / 78`.

Required local checks passed:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-object-owner-boundary-currentbase.php
php -r 'json_decode(...)' lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

## Non-Overlap

This does not repeat accepted xref table offset-owner rejection, object-stream carrier exclusion, object-stream base preservation, type-2 member-index repair, xref-stream `/Prev` sparse `/Index` row precedence, hybrid xref table free-entry precedence, stream dictionary name escaping, stale `/Length` payload recovery, or stream-filter object-boundary fallback selection.

The new behavior is specifically the direct-object owner boundary for xref stream objects: fake xref-stream object headers embedded inside another stream's declared payload cannot own `startxref` or redirect current-base page extraction.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, stream dictionary parser, xref stream parser, page-tree walker, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
