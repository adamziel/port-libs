# markerPDF parser xref-stream compressed operand owner current-base

Micro-slice: `parser-rebase-xref-stream-compressed-operand-owner-currentbase`
Session: `port-dev-markerpdf-parser73-20260602T221545Z`
Base accepted HEAD: `e125b1864e3d759fe9909dd2c4d72359f1c4fbdb`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()` into `pdftext.extraction.dictionary_output(...)`, and `naive_get_text()` delegates page text extraction to pypdfium. Source: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>

At the PDF parser boundary, PDFium validates `/Type /ObjStm`, `/N`, and `/First`, loads filtered object-stream data, reads object-number/offset pairs, and parses the selected member object by archive index. Source: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/parser/cpdf_object_stream.cpp>

## Behavior

An xref stream can declare stream dictionary operands such as `/Filter` and `/DecodeParms` through indirect generation-zero objects. When those helper objects are current compressed members of a direct object stream, the native parser must recover them before the xref stream can decode and build the final owner map.

`PdfTextExtractor::objectsWithDirectStreamDictionaryOperandOwners()` now has a bounded compressed-helper path for xref-stream operand resolution:

- only generation-zero helpers are considered;
- only direct `/ObjStm` carriers before the owning xref stream offset are scanned;
- helper member bodies must pass the existing safe operand-body guard;
- newer compressed helper storage wins over older direct same-number helper objects.

`extractXrefStreamFilterLengthOwnerReview()` now rebuilds live direct objects and compressed members after xref decoding so review metadata reports the same current owner that text extraction uses.

The focused fixture keeps stale direct `30 0` and `31 0` helper objects (`/ASCIIHexDecode` and malformed DecodeParms) before a current direct object stream that carries compressed helper members `30` (`/FlateDecode`) and `31` (`null`). The latest xref stream uses `/Filter 30 0 R /DecodeParms 31 0 R`. WordPress extraction emits only the current page text and records `filter_owner_policy=compressed_operand_after_xref_decode`.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves xref-stream Filter operands from current compressed object-stream helpers

1 test files, 32 assertions, 0 failures
```

Adjacent parser/xref operand gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterLengthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamStreamDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamFilterDictGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamNestedFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamDictionaryXrefOwnerRecoveryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamFilterDecodeParmsCurrentBaseTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 197 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-compressed-operand-owner-currentbase.php
```

The smoke emitted two Gutenberg paragraphs, `Current compressed xref operand page` and `Compressed Filter operand selected`, with `uses_current_compressed_xref_operand_page=true`, `selects_compressed_filter_operand=true`, `excludes_stale_compressed_xref_operand_page=true`, `excludes_stale_direct_filter_operand=true`, `xref_selected_operand_count=1`, `unresolved_operand_count=0`, `decoded_entry_count=8`, `filter_owner_policy=compressed_operand_after_xref_decode`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax checks passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserXrefStreamCompressedOperandOwnerCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-compressed-operand-owner-currentbase.php
```

## Status Delta

`lane-status.json` moves `phpPass` and `wordpressScenarios` from `899` to `900`. `UPSTREAM_TEST_MANIFEST.json` moves mapped current-base behavior from `634` to `635 / 78` and records `pdfParserXrefStreamCompressedOperandOwnerCurrentBase`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted exact-generation direct xref-stream `Length`/`Filter`/`DecodeParms` owner recovery, object-stream stream-dictionary generation helpers, object-stream indirect filter-chain recovery, nested filter-array fail-closed behavior, xref-stream DecodeParms predictor handling, stream-owned xref object rejection, object-stream member-index repair, previous carrier generation rebuild, or current carrier free-row preservation.

The bounded behavior is specifically current compressed object-stream helper operands used by an xref stream before the final xref owner map exists.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct object scanner, direct object-stream decoder, xref-stream decoder, stream-filter dispatcher, object-stream expander, page-tree walker, text-token extractor, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, `pypdfium2`/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
