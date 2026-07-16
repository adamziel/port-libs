# markerPDF parser stream-dictionary xref-owner recovery current base

Micro-slice: `parser-stream-dictionary-xref-owner-recovery-currentbase`
Session: `port-dev-markerpdf-parser67-20260602T214835Z`
Base accepted HEAD: `46b872b82e6663ed85da04f0c1274e2577b1e5b5`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes structured PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level PDF parsing to `pdftext.extraction.dictionary_output(...)`, and routes `naive_get_text()` through pypdfium page text extraction. Source: https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py

Native implication for this slice: xref streams are stream dictionaries, but their rows must be decoded before the final current object-owner map exists. The stream dictionary's `/Length`, `/Filter`, and `/DecodeParms` helper references still carry exact object generations; later stale same-number helpers must not be used to decode the xref rows that select the current catalog/page tree.

## Behavior

`PdfTextExtractor::xrefStreamEntriesFromDefinition()` now seeds xref-stream row decoding with exact direct helper bodies for `/Length`, `/Filter`, and `/DecodeParms` references from the xref stream dictionary. This recovery is local to xref-stream row decoding and does not change ordinary content-stream helper resolution through the final current owner map.

The focused fixture builds:

- current catalog/page/content objects selected by an xref stream;
- current helper objects `7 0` encoded row length, `8 0` `/FlateDecode`, and `9 0` predictor DecodeParms;
- an xref stream dictionary using `/Length 7 0 R`, `/Filter 8 0 R`, and `/DecodeParms 9 0 R`;
- later stale helper objects `7 1` short length, `8 1` `/ASCIIHexDecode`, and `9 1` malformed `/Predictor /Twelve`;
- a later stale `1 1` catalog that wins if the xref stream cannot decode.

Before the fix, the preliminary object table resolved `/Filter 8 0 R` through stale `8 1`, the xref stream failed to decode, and extraction emitted stale page text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryXrefOwnerRecoveryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL recovers xref-stream dictionary helper owners before stale same-number helpers
Expected: array (
  0 => 'Current xref owner stream dictionary',
  1 => 'Recovered xref stream helpers',
)
Actual: array (
  0 => 'Stale xref owner stream dictionary leak',
)
1 test files, 1 assertions, 1 failures
```

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserStreamDictionaryXrefOwnerRecoveryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserStreamDictionaryXrefOwnerRecoveryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-xref-owner-recovery-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-xref-owner-recovery-currentbase.php
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryXrefOwnerRecoveryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers xref-stream dictionary helper owners before stale same-number helpers

1 test files, 11 assertions, 0 failures
```

Adjacent parser/xref/text gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserStreamDictionaryXrefOwnerRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterXrefOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDecodeParmsOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterObjectBoundaryTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryEscapeBoundaryTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamObjectOwnerFreeEntryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 10 selected test files (root lock skipped)
10 test files, 716 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-parser-stream-dictionary-xref-owner-recovery-currentbase.php
```

The smoke emits two current paragraphs, `Current xref owner stream dictionary` and `Recovered xref stream helpers`, and reports `uses_current_xref_owner_stream_dictionary=true`, `recovers_xref_stream_helper_owners=true`, `stale_xref_owner_page_excluded=true`, `stale_helper_names_excluded=true`, `fake_xref_dictionary_owner_excluded=true`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

Status delta: behavior tests `870 -> 871`; mapped markerPDF parser semantics `614 -> 615 / 78`.

## Non-Overlap

This does not repeat accepted xref-table stream dictionary owner selection, stale-generation Filter rejection after the current owner map exists, xref-stream filter DecodeParms predictor rows with direct operands, stream-owned xref object rejection, xref-stream free-entry owner review, stream dictionary escaped-name parsing, stream-length startxref recovery, or object-stream filter-chain owner recovery.

The bounded behavior is specifically xref-stream dictionary helper owner recovery while decoding xref rows before the final owner map is available.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, exact-generation direct object definitions, xref stream parser, Flate predictor decoder, page-tree walker, text-token extractor, and WordPress smoke path. Full upstream markerPDF runner parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
