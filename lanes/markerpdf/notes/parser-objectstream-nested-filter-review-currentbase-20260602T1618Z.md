# markerPDF parser object-stream nested filter review current-base

Micro-slice: `parser-objectstream-nested-filter-review-currentbase-20260602T1618Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes native page text through `marker/pdf/extract_text.py::get_text_blocks()` using `pdftext.extraction.dictionary_output(...)` and through `naive_get_text()` using pypdfium page text. That makes PDFium/pdftext parser behavior source truth for object streams, xref streams, and stream-filter decoding before WordPress paragraphs are emitted.

For PDF stream filters, the valid `/Filter` grammar is a filter name, `null`, an indirect filter name/array, or a flat array of names/null entries. A nested array inside that filter array is malformed. The native parser now fails closed on that nested array instead of flattening it into a supported chain.

## Behavior

`PdfTextExtractor::filterNamesFromValue()` now accepts top-level indirect filter arrays, but rejects nested filter arrays inside an already-open filter array. `allDecodedStreams()` also excludes `/Type /XRef` streams from fallback page text, matching the existing object-stream/image-stream exclusions.

The focused fixture builds a current xref stream that selects an object stream with `/Filter [ /ASCIIHexDecode [ /FlateDecode ] ]`. Before the fix, the nested array was flattened, so the malformed object stream created an empty page tree and suppressed a safe direct fallback stream. After the fix, the object stream is rejected, the xref stream payload stays out of fallback text, and the WordPress fallback emits only `Direct fallback survives nested filter review`.

## Evidence

Red baseline before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php
FAIL rejects nested object stream filter arrays before WordPress fallback text extraction
Expected: array (
  0 => 'Direct fallback survives nested filter review',
)
Actual: array (
)
1 test files, 1 assertions, 1 failures
```

Focused green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php
1 test files, 10 assertions, 0 failures
```

Adjacent parser/xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
7 test files, 644 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-object-stream-nested-filter-currentbase.php
nested_filter_object_stream_rejected=true
direct_fallback_visible=true
xref_stream_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-object-stream-nested-filter-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-object-stream-nested-filter-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

Status delta: behavior tests `544 -> 545`; mapped parser semantics `391 -> 392 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted indirect object-stream `/Length`/`/Filter`/`/N`/`/First` recovery, iterative helper object-stream filter-chain recovery, stream-filter DecodeParms fail-closed behavior, xref-stream filter DecodeParms predictor decoding, stream dictionary escaped-name parsing, object-stream nested-token boundaries, object-stream carrier fallback exclusion, or hybrid/xref object-stream generation precedence.

The new behavior is specifically malformed nested filter arrays on xref-selected object streams, plus the fallback exclusion of xref-stream payloads exposed by that fail-closed path.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref stream parser, stream filter-chain resolver, object-stream decoder, fallback stream extractor, content-token text extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
