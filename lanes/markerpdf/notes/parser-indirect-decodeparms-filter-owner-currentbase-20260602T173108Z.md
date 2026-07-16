# markerPDF parser indirect DecodeParms Filter owner current-base

Micro-slice: `parser-indirect-decodeparms-filter-owner-currentbase-20260602T173108Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes PDF text through `marker/pdf/extract_text.py`: `get_text_blocks()` delegates low-level parsing to `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` uses pypdfium page text extraction. The local markerPDF upstream cache is absent in this worker, so this slice uses the pinned raw source boundary already recorded in lane notes: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

The native PHP parser therefore owns the dependency boundary where a PDF page content stream's `/Filter` and `/DecodeParms` operands are resolved before bytes become WordPress paragraph text.

## Behavior

The focused fixture stores current indirect helper objects before the owner stream:

- `20 0 obj /FlateDecode`
- `21 0 obj << /Predictor 1 >>`
- `4 0 obj << /Filter 20 0 R /DecodeParms 21 0 R >> stream ...`

The Flate stream is a zlib stored block whose owner bytes physically contain newline-delimited `endstream`, `endobj`, and fake `20 0 obj` / `21 0 obj` helper definitions before the real visible text. Later stale definitions for objects 20 and 21 are present, but the xref table selects the current helpers.

Before this patch, the initial direct-object scanner could not verify filtered stream candidates when `/Filter` and `/DecodeParms` were indirect because it had not built the object map yet. That allowed the embedded fake `endstream` to terminate the owner stream early. `PdfTextExtractor` now resolves only simple helper objects referenced by top-level `/Filter` and `/DecodeParms` that are fully completed before the owner stream, then uses the existing filtered-stream verifier. Final decoding still uses the xref-selected object map.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps indirect Filter and DecodeParms helpers bound to the current stream owner

1 test files, 11 assertions, 0 failures
```

Adjacent parser gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
PASS keeps indirect Filter and DecodeParms helpers bound to the current stream owner
PASS rejects stream-owned fake DecodeParms objects before current-base WordPress text extraction
PASS uses current xref direct stream objects before filtered fallback text extraction
PASS ignores nested stream-looking tokens inside current stream payload boundaries
PASS recovers xref-selected object streams whose filter chain operands are compressed helpers
PASS applies xref stream filter DecodeParms before current-base object selection
PASS reviews xref-stream indirect Filter and Length owners before current-base WordPress text extraction

6 test files, 85 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-indirect-filter-decodeparms-owner-currentbase.php
```

The smoke emits three Gutenberg paragraphs: `Indirect Filter Current Owner`, `DecodeParms Current Helper`, and `Fake Stream Objects Excluded`, with `uses_current_indirect_filter=true`, `uses_current_indirect_decodeparms=true`, `fake_stream_owned_helper_excluded=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-indirect-filter-decodeparms-owner-currentbase.php
git diff --check -- lanes/markerpdf
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted simple indirect `/DecodeParms` owner scanning, direct fallback stream object-boundary selection, xref stream filter DecodeParms predictor decoding, xref-stream indirect Filter/Length owner review, object-stream indirect filter-chain operand recovery, nested object-stream filter fail-closed fallback, stream dictionary escaped-name parsing, inline image abbreviation DecodeParms handling, or generic stream-filter DecodeParms fail-closed validation.

The new behavior is specifically initial direct-object owner scanning for ordinary page content streams whose `/Filter` and `/DecodeParms` operands are both indirect and whose compressed owner bytes contain fake helper-object boundaries.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF direct-object scanner, dictionary/value parser, stream filter dispatcher, Flate decoder, DecodeParms validator, xref-selected object map, page-tree walker, content-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/rendering helpers.
