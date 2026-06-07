# markerpdf xref-stream row-alignment object-stream current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF obtains searchable PDF page text from the native PDF parser before model/OCR stages. In the no-GPU PHP lane, malformed xref-stream ownership must therefore fail closed before object-stream expansion can expose stale or unsafe page text to WordPress imports.
- PDF xref-stream decoded rows are fixed-width records defined by `/W`. A decoded stream whose byte length is not an exact multiple of the row width is malformed; this slice rejects that current xref stream before decoding any type-2 compressed-object rows.

## Implementation

- `PdfTextExtractor::xrefStreamEntriesFromDefinition()` now returns no entries when decoded xref-stream bytes have trailing data that is not aligned to the `/W` row width.
- `PdfTextExtractor::startxrefXrefStreamFilterDecodeFailed()` now treats unaligned decoded xref-stream rows as an admission failure for the selected startxref stream, so the native parser does not silently consume a complete prefix.
- `PdfTextExtractor::extractXrefObjectStreamIndexReview()` now reports `malformed_xref_stream_row_alignment_count` and `malformed_xref_stream_row_alignment_entries` with `owner_policy=unaligned_xref_stream_row_width`, decoded length, entry width, complete row count, and trailing byte count.
- Added a focused fixture where the selected xref stream advertises catalog/pages/page objects as type-2 members of an object stream, then appends one trailing decoded byte after six valid `/W [1 4 1]` rows. The malformed xref stream now yields no imported text and records review-only row-alignment metadata.
- Added a WordPress smoke that verifies `visible_text_empty=true`, `compressed_member_suppressed=true`, `malformed_xref_stream_row_alignment_count=1`, `row_alignment_owner_policy=unaligned_xref_stream_row_width`, and no Python/model/OCR or external PDF tool execution.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamRowAlignmentObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on unaligned xref-stream rows before object-stream expansion
Values are not identical
Expected: array (
)
Actual: array (
  0 => 'Malformed row-alignment object-stream page leak',
  1 => 'Trailing xref byte ignored',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamRowAlignmentObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on unaligned xref-stream rows before object-stream expansion

1 test files, 20 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStream*CurrentBaseTest.php
Focused test run: 66 selected test files (root lock skipped)
66 test files, 1354 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-row-alignment-object-stream-currentbase.php
visible_text_empty=true
compressed_member_suppressed=true
malformed_xref_stream_row_alignment_count=1
row_alignment_owner_policy=unaligned_xref_stream_row_width
decoded_length=37
entry_width=6
trailing_byte_count=1
executes_python_or_models=false
executes_external_pdf_tools=false
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted object-stream member-index repair, duplicate xref-stream row ownership, unsupported type-2 entry suppression, malformed xref-stream width rejection, malformed xref-stream `/Index` repair, zero-width type-2 index recovery, object-stream carrier base preservation, current free-carrier repair, inherited carrier reuse, incomplete object-stream header failure, xref-stream `/Prev` hybrid owner repair, xref-stream free-entry suppression, classic xref rebuild, or stream filter operand-owner slices. The bounded behavior is only decoded-row byte alignment for the current startxref-selected xref stream before object-stream expansion.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, stream filter decoder, xref-stream parser, object-stream expansion guard, review metadata path, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI model workers, JavaScript/PDF action execution, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
