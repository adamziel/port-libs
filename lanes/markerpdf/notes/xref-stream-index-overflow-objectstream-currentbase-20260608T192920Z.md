# markerPDF xref-stream Index overflow object-stream current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260608T192920Z`

Base: `520f0ce7b08b30848beed1a62b07a69292c33e03`

## Source Truth

Upstream markerPDF routes searchable PDF text through pdftext/PDFium parser ownership before OCR/model stages. In the native no-GPU PHP scope, PDF xref-stream `/Index` ranges, trailer `/Root` references, and object-stream member header object numbers must not be allowed to alias oversized integers through PHP casts before WordPress paragraph extraction.

PDF object numbers are implementation-bounded in this lane. If an `/Index` start object or indirect-reference object number overflows PHP integer bounds, the parser should fail closed instead of casting to `PHP_INT_MAX` and promoting a malformed compressed catalog.

## Implementation

`PdfTextExtractor` now routes:

- xref-stream strict integer array values through `pdfBoundedIntegerToken()`;
- PDF unsigned integer tokens used by indirect references and object-stream headers through the same bounded parser.

This prevents oversized `/Index` values, oversized trailer `/Root` references, and oversized object-stream header object numbers from converging on the same saturated PHP integer key.

## Evidence

Pre-fix focused run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamIndexOverflowObjectStreamCurrentBaseTest.php`

Result: `1 test files, 1 assertions, 1 failures`. The malformed PDF imported `Oversized Index object-stream root leak` and `Integer alias page imported`.

After fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamIndexOverflowObjectStreamCurrentBaseTest.php`

Result: `1 test files, 18 assertions, 0 failures`.

Adjacent xref/object-stream regression run:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefStreamIndexOverflowObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamMalformedIndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamIndirectIntegerArrayElementsCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamSignedHeaderCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamPlusHeaderReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamOverflowFieldObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamRowAlignmentObjectStreamCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamTruncatedIndexObjectStreamCurrentBaseTest.php`

Result: `8 test files, 174 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-stream-index-overflow-objectstream-currentbase.php`

Result: exits 0 with `malformed_index_rejected=true`, `blocks_object_stream_root_alias=true`, `excluded_oversized_root_text=true`, `compressed_entry_count=0`, `index_owner_policy=non_integer_xref_stream_index_value`, `rejected_before_row_decode=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted xref-stream row-field overflow handling, negative `/Index` values, truncated `/Index` rows, row alignment checks, indirect integer `/W` and `/Index` helper selection, plus-signed object-stream headers, stale carrier replacement, type-2 carrier repair, object-stream member-offset token boundaries, or annotation/metadata/attachment object-stream extraction. The bounded behavior is only oversized xref `/Index` object-number ranges and matching oversized object-reference/header aliases before object-stream root promotion.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, bounded integer parser, xref-stream decoder, object-stream expander, page-tree extraction, and WordPress smoke harness. Live OCR, Surya/Texify/Torch model execution, pypdfium/PDFium rendering, decryption, JavaScript/action execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
