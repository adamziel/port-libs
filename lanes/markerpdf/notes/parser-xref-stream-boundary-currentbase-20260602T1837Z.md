# markerPDF parser xref-stream boundary current base

Micro-slice: `parser-xref-stream-boundary-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py::get_text_blocks()` with `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` delegates page text extraction to pypdfium. The PHP lane therefore owns the native parser/dependency boundary for xref traversal before WordPress paragraphs are emitted.

PDF parser behavior for this slice: `startxref` is a file-level trailer marker for a cross-reference table or stream. A `startxref` byte sequence inside another indirect object's declared stream payload is not a trailer marker and must not redirect current xref-stream object selection.

## Behavior

`PdfTextExtractor::latestStartxrefOffset()` now accepts direct-object definitions and ignores `startxref` tokens whose token offset is inside a direct object's body. Current xref table/stream chain callers pass the definitions they already built, so a fake later `startxref` inside stream payload bytes no longer overrides the real current trailer marker.

The focused fixture builds:

- a current page tree selected by a real xref stream and real `startxref`;
- stale page objects plus a stale xref stream appended later;
- a carrier stream whose payload contains `startxref` pointing at the stale xref stream.

Before the fix, the global regex selected the stream-owned token and emitted stale page text. After the fix, WordPress extraction emits only `Current startxref token page` and `Stream token ignored`.

## Evidence

Red probe before source repair:

```text
array (
  0 => 'Stale stream-owned startxref page',
  1 => 'Fake latest token leak',
)
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores stream-owned startxref tokens before xref-stream current-base selection

1 test files, 10 assertions, 0 failures
```

Adjacent xref/parser gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevHybridOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefIncrementalObjectStreamFreeRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamFreeEntryPrevCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridReferenceRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserTrailerEncryptIdPrecedenceCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 17 selected test files (root lock skipped)
17 test files, 788 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-xref-stream-boundary-currentbase.php
```

The smoke emits `uses_current_startxref_token_page=true`, `stream_owned_startxref_token_ignored=true`, `excluded_stale_stream_owned_startxref_page=true`, `excluded_fake_latest_token_leak=true`, `carrier_payload_excluded=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserXrefStreamBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-parser-xref-stream-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $f) { json_decode(file_get_contents($f), true, 512, JSON_THROW_ON_ERROR); echo "$f json ok\n"; }'
git diff --check -- lanes/markerpdf
```

All required checks passed.

Status delta: behavior tests `645 -> 646`; mapped parser semantics `471 -> 472 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted stream-owned xref table offsets, stream-owned fake xref-stream object headers, invalid explicit xref-stream offsets, xref-stream filter DecodeParms, indirect xref-stream Filter/Length owner review, object-stream filter-chain helper recovery, `/Prev` generation repair, hybrid xref object-stream owner precedence, or the existing appended object-stream rebuild guard.

The bounded behavior is specifically the `startxref` token boundary: a file-level current xref stream remains authoritative when a later direct stream payload contains a fake `startxref` token pointing at stale xref-stream rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref stream parser, stream owner boundary model, page-tree walker, stream decoder, content-token extractor, and WordPress smoke path. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
