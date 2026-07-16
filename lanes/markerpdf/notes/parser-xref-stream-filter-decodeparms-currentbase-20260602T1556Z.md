# markerPDF parser xref-stream filter DecodeParms current-base

Micro-slice: `parser-xref-stream-filter-decodeparams-currentbase-20260602T1556Z`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF object/xref/stream parsing to `pdftext` and pypdfium. Source: https://github.com/sddai/markerPDF/blob/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

This native PHP boundary therefore needs to decode xref streams before choosing the page-tree objects that become WordPress paragraphs.

PDF xref streams are ordinary stream dictionaries for the stream-filter layer: `/Filter` chains and `/DecodeParms` predictor dictionaries apply to the xref entry bytes before `/W` and `/Index` rows are interpreted.

## Behavior

The focused fixture stores current generation-0 catalog/page/content objects before stale generation-1 replacements. The latest `startxref` points at an xref stream whose bytes are encoded as ASCIIHex then Flate, and whose Flate output uses PNG predictor rows with `/DecodeParms [ null << /Predictor 12 /Columns 6 >> ]`.

When the xref-stream filter chain and DecodeParms are applied, rows select the current generation-0 page tree and exclude the stale generation-1 page. If predictor rows are not applied first, current-base object selection falls back to stale latest-generation objects.

No production parser change was needed in this slice: `PdfTextExtractor` already routes xref streams through the shared stream-filter decoder. The new focused test and WordPress smoke lock this boundary so future parser work cannot regress it.

## Evidence

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)

1 test files, 10 assertions, 0 failures
```

Adjacent parser/xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfObjectStreamLengthFilterTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserXrefOffsetOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectStreamGenerationRepairTest.php lanes/markerpdf/tests/PdfXrefHybridObjectStreamGenerationTest.php lanes/markerpdf/tests/PdfObjectStreamXrefFreeEntryConflictTest.php lanes/markerpdf/tests/PdfXrefObjectStreamTrailerBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfObjectStreamNestedTokenBoundaryTest.php
Focused test run: 14 selected test files (root lock skipped)

14 test files, 706 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-filter-decodeparms-currentbase.php
```

The smoke metadata emits `uses_current_xref_stream_decodeparms_page=true`, `applies_xref_stream_predictor_rows=true`, `excludes_stale_generation_page=true`, `excludes_xref_dictionary_tokens=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Required checks:

```text
php -l lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-filter-decodeparms-currentbase.php
jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json
git diff --check -- lanes/markerpdf
```

Status delta: behavior tests `532 -> 533`; mapped parser semantics `379 -> 380 / 78`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream indirect `/Length`/`/Filter`/`/DecodeParms` recovery, iterative object-stream helper expansion, xref-stream `/Prev` generation repair, hybrid xref object-stream generation precedence, xref-stream type-2 object-stream base/index repair, stream dictionary name escaping, fallback stream object-boundary selection, stream-filter fail-closed DecodeParms boundaries, or ordinary content-stream predictor decoding.

The new evidence is specifically current startxref xref-stream row decoding where the xref stream itself has a filter chain plus DecodeParms predictor rows before current-base object selection.

## Dependency Closure

No new support component is needed. This slice reuses the native direct-object scanner, startxref/xref stream parser, stream filter-chain dispatcher, DecodeParms predictor decoder, page-tree walker, content-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
