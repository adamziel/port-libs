# markerPDF xref object-stream explicit-zero carrier current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T165651Z`
Session: `port-dev-markerpdf-object-xref-20260605T165651Z`
Base accepted HEAD: `5417c5c77ed7abafc4aa8f6b8401abfd58981dad`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable-PDF text extraction through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level PDF 1.5 xref stream and object stream ownership to pdftext/PDFium. In the native no-GPU PHP path, xref-stream type-2 rows must therefore own the current object state before fallback page text import, even when the row is malformed.

PDF xref-stream type-2 field two is the containing object stream number. A decoded row with an explicit carrier value of `0` is not a valid object-stream reference. The row must still suppress stale `/Prev` direct objects for the same object number, but review metadata should distinguish an explicit bad carrier from the accepted zero-width carrier-default boundary.

## Change

`PdfTextExtractor::extractXrefObjectStreamIndexReview()` now reports:

- `invalid_explicit_object_stream_carrier_count`
- `object_stream_owner_policy=invalid_explicit_object_stream_carrier`

for type-2 rows where `/W` explicitly decodes field two as `0`. Zero-width field-two defaults continue to report `missing_object_stream_carrier`, so existing zero-width carrier diagnostics remain stable.

The focused fixture builds a previous classic xref table with stale direct page object `4 0 R`, then appends a latest xref stream with `/Prev`, `/Index [4 1]`, `/W [1 4 1]`, and a type-2 row whose explicit carrier is `0`. WordPress-visible text imports only the current guard page, stale direct text and the unrelated object-stream member stay excluded, and the review row records the explicit invalid-carrier policy.

## Evidence

Red-first focused run before the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamExplicitZeroCarrierCurrentBaseTest.php`

Result: `1 test files, 15 assertions, 1 failures`; the new case failed because `invalid_explicit_object_stream_carrier_count` was absent.

Focused run after the source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamExplicitZeroCarrierCurrentBaseTest.php`

Result: `1 test files, 25 assertions, 0 failures`.

Adjacent object-stream/xref parser family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamExplicitZeroCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamZeroWidthCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentCarrierRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamCurrentFreeCarrierRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamOmittedCarrierCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamType2IndexCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamDuplicateZeroWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefObjectStreamIndexZeroWidthMemberReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfXrefStreamUnsupportedTypeObjectStreamCurrentBaseTest.php`

Result: `9 test files, 176 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-explicit-zero-carrier-currentbase.php`

Result: emits `uses_current_guard_page=true`, `stale_direct_page_suppressed=true`, `stale_object_stream_member_suppressed=true`, `zero_width_object_stream_entry_count=0`, `unresolved_object_stream_carrier_count=1`, `invalid_explicit_object_stream_carrier_count=1`, `object_stream_field_is_explicit=true`, `object_stream_field_is_zero_width=false`, `invalid_object_stream_carrier_rejected=true`, `object_stream_owner_policy=invalid_explicit_object_stream_carrier`, `page_count=1`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP direct-object scanner, xref table/stream parser, `/Prev` traversal, object-stream expander, page-tree walker, content-token extractor, and WordPress smoke path. GPU/OCR/model execution, pdftext, pypdfium2/PDFium, PIL, Surya/Torch, Texify, Streamlit/FastAPI model workers, benchmark/model downloads, and external PDF tools were not run and remain intentionally out of scope for this no-GPU markerPDF lane.

## Non-overlap

This does not repeat accepted zero-width object-stream carrier defaults, zero-width member-index recovery, duplicate zero-width member ambiguity, malformed `/W` fail-closed handling, unsupported xref-stream entry type suppression, object-stream current/free carrier repair, omitted carrier inference, explicit type-2 member-index selection, duplicate member offsets, invalid member offsets, stream-member rejection, object-stream generation repair, hybrid table/free precedence, or stream-owned fake xref/startxref rejection. The bounded behavior here is only explicit field-two carrier value `0` in a type-2 xref-stream row.
