# markerpdf xref-stream overflow field object-stream current-base

## Source Truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF obtains searchable PDF page text from the PDF parser layer before model/OCR stages. In the native no-GPU PHP lane, selected xref-stream rows must be decoded without native integer overflow before object ownership, `/Prev` inheritance, or object-stream expansion is trusted.
- PDF xref-stream `/W` field widths can describe multi-byte row fields. The native PHP port can accept fitting values, but a row field whose byte value exceeds `PHP_INT_MAX` cannot be represented exactly and must fail closed rather than wrap into an offset, object-stream number, generation, or index.

## Implementation

- `PdfTextExtractor::xrefFieldValue()` now accumulates bytes with a `PHP_INT_MAX` guard instead of shifting into overflow.
- `PdfTextExtractor::xrefStreamRowValueOverflowProblem()` scans decoded xref-stream rows using the declared `/W` and `/Index` ranges and records the first overflowing row field.
- Current xref-stream decoding now aborts when any row field overflows, and the startxref-chain merge path returns no entries before stale `/Prev` rows can be inherited.
- `extractXrefObjectStreamIndexReview()` exposes the overflow under `malformed_xref_stream_width_entries` with owner policy `overflowing_xref_stream_field_value`, `malformed_width_indexes`, row/object/field metadata, and `rejected_before_row_decode=true`.
- Added a focused fixture where the latest xref stream has valid 8-byte direct rows plus one unrelated overflowing 8-byte field. Before the fix the valid current page row was still accepted; after the fix the selected stream is rejected and both current partial-row text and stale previous object-stream text remain excluded.
- Added a WordPress smoke that reports overflow review metadata and proves no Python/model/external PDF tool execution.

## Red-First Evidence

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamOverflowFieldObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects overflowing xref-stream row fields before object-stream fallback text
Values are not identical
Expected: array (
)
Actual: array (
  0 => 'Current oversized xref field guard page',
)

1 test files, 1 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamOverflowFieldObjectStreamCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects overflowing xref-stream row fields before object-stream fallback text

1 test files, 24 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamRowAlignmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamOverflowFieldObjectStreamCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
PASS rejects malformed negative xref-stream Index values before object-stream fallback text
PASS rejects malformed negative xref-stream W byte widths before object-stream fallback text
PASS rejects overflowing xref-stream row fields before object-stream fallback text
PASS fails closed on unaligned xref-stream rows before object-stream expansion

4 test files, 82 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStream*CurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefObjectStream*CurrentBaseTest.php
Focused test run: 81 selected test files (root lock skipped)
81 test files, 1780 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-xref-stream-overflow-field-currentbase.php
exits 0 with malformed_xref_stream_width_count=1, malformed_width_owner_policy=overflowing_xref_stream_field_value, malformed_width_indexes=[1], overflow_object_number=12, overflow_row_index=7, overflow_field_index=1, overflow_field_width=8, visible_text_empty=true, current_text_excluded=true, stale_object_stream_text_excluded=true, stale_direct_text_excluded=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfXrefStreamOverflowFieldObjectStreamCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-xref-stream-overflow-field-currentbase.php
No syntax errors detected
```

```text
git diff --check -- lanes/markerpdf
exits 0
```

Root harness was not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted negative `/W` validation, negative `/Index` validation, row-alignment and truncated-row guards, duplicate xref-stream row handling, indirect `/W` and `/Index` operand ownership, compressed helper operands, object-stream header parsing, object-stream member index repair, object-stream offset/token-boundary rejection, omitted object-stream carrier repair, inherited carrier reuse, current carrier generation repair, previous hybrid owner suppression, stream-member rejection, or stale fallback repair. The bounded behavior is only native integer overflow detection while decoding selected xref-stream row fields.

## Dependency Closure

No new support component is needed. This reuses the native PHP object scanner, xref-stream decoder, stream filter decoder, object-stream expander, xref review metadata, text extractor, and WordPress smoke path. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium, PIL, Streamlit/FastAPI workers, JavaScript/PDF action execution, decryption/password validation, signing/signature validation, and external PDF tools remain intentionally out of scope under the no-GPU markerPDF directive.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
