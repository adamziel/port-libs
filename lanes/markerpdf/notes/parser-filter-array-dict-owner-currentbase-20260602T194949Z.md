# Parser Filter-Array Dictionary Owner Current Base

## Source Truth

- Upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes text extraction through `marker/pdf/extract_text.py`, where the low-level PDF dictionary/page extraction boundary is delegated to `pdftext`/pypdfium-style parsing.
- The native PHP parser owns the bounded stream-filter decoding path before WordPress paragraph output. A PDF stream `/Filter` array must resolve to decoder names or null entries; a direct dictionary entry is not a decoder operand.

Reference: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

## Behavior

- Added a focused fixture with `/Filter [ << /Owner (...) /Fake [ /Nested ] >> /FlateDecode ]`.
- The malformed filtered stream contains decoded bytes with fake `endstream`, `endobj`, and `20 0 obj` owner markers plus hidden text.
- The parser now has regression coverage proving that malformed filter array fails closed, the decoded fake owner payload is not visible, and the adjacent valid current page content stream still imports as `Safe current page text`.
- No production parser edit was needed on this base because `PdfTextExtractor::filterNamesFromValue()` already rejects dictionary entries inside filter arrays.

## Non-Overlap

This slice does not repeat indirect Filter/DecodeParms owner resolution, xref generation matching, direct stream dictionary escape parsing, object-stream filter-owner exclusion, inline image stream-owner recovery, inline image filter-array abbreviation/null handling, nested object-stream filter arrays, stream filter error boundaries, or xref offset owner recovery. It is limited to an ordinary page content stream whose direct `/Filter` array contains a dictionary entry before a valid decoder name.

## Evidence

- `php -l lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-parser-filter-array-dict-owner-currentbase.php` passed.
- `jq empty lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json lanes/markerpdf/lane-status.json` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php` passed with 1 test file, 12 assertions, and 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserFilterArrayDictOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserIndirectDecodeParmsFilterOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed with 9 test files, 688 assertions, and 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-parser-filter-array-dict-owner-currentbase.php` emitted one Gutenberg paragraph, `Safe current page text`, and smoke flags `filter_array_dictionary_rejected=true`, `hidden_filtered_payload_excluded=true`, `fake_dictionary_owner_excluded=true`, `safe_current_page_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF object scanner, array/dictionary token parser, stream-filter dispatcher, page-tree walker, and content stream tokenizer. Full upstream parity remains blocked on the heavier Python stack: `pdftext`, pypdfium/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering adapters.
