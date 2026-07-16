# markerPDF xref object-stream duplicate zero-width member boundary

Micro-slice: `xref-object-stream-currentbase`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes page text through `marker/pdf/extract_text.py::get_text_blocks()`, which delegates low-level PDF parsing to `pdftext.extraction.dictionary_output(...)`; `naive_get_text()` delegates to pypdfium page text extraction.

PDFium stores xref-stream type-2 rows as object-stream number plus member index, then parses the indexed object-stream member and validates that the member header object number matches the requested object. When `/W` omits the third field, the strict member index defaults to `0`. The PHP lane already exposes a current-base recovery path for unique header-object matches when that omitted strict index would reject; this slice closes the ambiguous duplicate-header case.

## Behavior

`PdfTextExtractor::objectsFromObjectStreams()` now recovers a zero-width type-2 member by object-stream header object number only when that object number appears exactly once in the object stream. If the strict default index points at another object and the requested object number appears more than once later in the object-stream header, the compressed member is skipped instead of choosing the last duplicate payload.

`PdfTextExtractor::extractXrefObjectStreamIndexReview()` now reports `ambiguous_zero_width_member_count`, `matching_header_object_number_count`, `duplicate_header_object_number`, `ambiguous_zero_width_member`, and `selection_policy=ambiguous_zero_width_duplicate_header_object_number` for that review-only boundary.

The focused WordPress fixture keeps a valid direct current page, then adds a malformed current object stream with member index `0` for object `12` and two later duplicate object `4` members. Before this repair, the native fallback could select duplicate object `4` text. After the repair, only these current page lines are emitted:

- `Current duplicate zero-width guard`
- `Ambiguous compressed page ignored`

## Evidence

Focused single test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on duplicate object-stream header numbers when xref member indexes are zero-width

1 test files, 17 assertions, 0 failures
```

Adjacent xref plus main extractor gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefPrevObjectStreamGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterChainCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 669 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-duplicate-zero-width-currentbase.php
uses_current_duplicate_zero_width_guard=true
ambiguous_compressed_page_ignored=true
excluded_stale_duplicate_member_page=true
excluded_duplicate_zero_width_member_leak=true
selection_policy=ambiguous_zero_width_duplicate_header_object_number
matching_header_object_number_count=2
ambiguous_zero_width_member_count=1
page_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

PHP lint passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-duplicate-zero-width-currentbase.php
```

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat explicit type-2 member-index selection, preserving the direct `/ObjStm` base object, unique zero-width index recovery by header object number, xref-stream `/Prev` generation repair, previous-carrier generation decoys, object-stream filter-chain operand recovery, current free-entry suppression of stale compressed members, or stream-owned xref/object owner boundaries.

The bounded behavior is specifically zero-width type-2 member-index recovery when the strict default index rejects and the requested object number appears multiple times in the same current object-stream header.

## Dependency Closure

No new support component is needed. The slice reuses native direct-object scanning, xref-stream parsing, object-stream decoding, page-tree walking, content-token extraction, review metadata, and the WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by `pdftext`, pypdfium/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.
