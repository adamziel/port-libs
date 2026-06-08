# markerPDF xref-stream malformed Size object-stream current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T212003Z`

Base: `68d7c32c04f00c8830ab48c497321c0c06937915`

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF obtains searchable PDF page text from parser-selected PDF objects before OCR/model stages. In the no-GPU PHP lane, malformed xref stream ownership must fail closed before object-stream rows or stale scanned direct objects can expose WordPress import text.
- For a PDF xref stream with no `/Index` array, `/Size` supplies the default row range. A missing, non-integer, or negative `/Size` cannot safely define that range, so this slice rejects the selected xref stream before row decoding.

## Implementation

- `PdfTextExtractor::xrefStreamSizeProblem()` now validates `/Size` strictly when `/Index` is absent. It resolves direct or referenced operands through the xref-stream dictionary operand-owner map, rejects missing, non-integer, and negative values, and leaves explicit `/Index` streams on the existing `/Index` validation path.
- `PdfTextExtractor::startxrefXrefStreamFilterDecodeFailed()` treats malformed size-without-index xref streams as selected-stream admission failures, preventing classic-table or direct-scan fallback from replacing the malformed current xref stream.
- `PdfTextExtractor::xrefStreamEntriesFromDefinition()` now rejects the same malformed size-without-index shape before row decoding in non-startxref and hybrid stream paths.
- `PdfTextExtractor::extractXrefObjectStreamIndexReview()` now reports `malformed_xref_stream_size_count` and `malformed_xref_stream_size_entries` with `owner_policy`, raw `size_value`, resolved size, and `rejected_before_row_decode=true`.
- Added a focused fixture where `/Size /BadSize` and omitted `/Index` would otherwise let decoded type-2 rows select an object-stream page and stale direct page text.
- Added a WordPress smoke that verifies malformed `/Size` suppresses direct and object-stream fallback text and uses no Python, OCR/model, multiprocessing, or external PDF tools.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamMalformedSizeObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed xref-stream Size without Index before object-stream fallback text
Values are not identical
Expected: array (
)
Actual: array (
  0 => 'Malformed Size direct fallback leak',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamMalformedSizeObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed xref-stream Size without Index before object-stream fallback text

1 test files, 66 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*ObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamMalformed*CurrentBaseTest.php
Focused test run: 62 selected test files (root lock skipped)
62 test files, 1489 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamCompressedSizeDefaultRangeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndexWidthOwnerReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamIndirectIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamIndirectIntegerArrayElementsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamPrevIndexWidthRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamMalformedSizeObjectStreamCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 206 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-malformed-size-objectstream-currentbase.php
<!-- markerpdf-xref-stream-malformed-size-objectstream-currentbase-smoke {"executes_python_or_models":false,"executes_external_pdf_tools":false,"native_boundary":"xref streams without /Index must provide a strict integer /Size before object-stream rows can select WordPress import text","malformed_size_rejected":true,"owner_policy":"non_integer_xref_stream_size_without_index","size_value":"/BadSize","resolved_size":null,"index_array_absent":true,"rejected_before_row_decode":true,"visible_text_empty":true,"direct_fallback_excluded":true,"object_stream_fallback_excluded":true,"compressed_entries_expanded":0} -->
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `/W` rejection, malformed `/Index` rejection, row-alignment rejection, row-field overflow rejection, truncated `/Index` rows, indirect `/W` and `/Index` helper resolution, compressed `/Size` helper default-range selection, zero-width type-2 index recovery, object-stream carrier repair, non-/ObjStm carrier rejection, or unsupported xref-stream row suppression. The bounded behavior is only strict `/Size` validation for xref streams that omit `/Index`.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, strict integer parser, xref-stream parser, stream filter decoder, object-stream expansion guard, review metadata path, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, JavaScript/action execution, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
