# markerPDF xref object-stream zero-width carrier current-base

Micro-slice: `markerpdf-object-stream-xref-parser-current-base-20260605T142622Z`

Session: `port-dev-markerpdf-object-xref-20260605T142622Z`

Base accepted HEAD: `6c126186066ceb7460fca9cb3fcff42503b6c891`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable PDF extraction through `marker/pdf/extract_text.py` into pdftext/PDFium-backed parsing before converter and WordPress-style import paths consume page text. Under the current no-GPU scope, this lane owns the native parser boundary for xref-stream and object-stream resolution before visible paragraphs are emitted.

PDF xref-stream type-2 rows use field two as the containing object-stream number and field three as the member index. A `/W` array with a zero-width second field defaults that object-stream number to `0`, which is not a valid object-stream carrier. The current row must still be treated as the current xref ownership state so stale `/Prev` direct objects do not leak into WordPress text.

## Implementation

`PdfTextExtractor` now preserves decoded type-2 rows whose object-stream-number field is zero-width/defaulted instead of dropping them when the field value is `0`. Those rows remain unresolved compressed-object rows, suppress stale direct objects for the same object number, and surface review metadata:

- `zero_width_object_stream_entry_count`
- `unresolved_object_stream_carrier_count`
- `object_stream_field_is_zero_width`
- `invalid_object_stream_carrier_rejected`
- `object_stream_owner_policy=missing_object_stream_carrier`

`PdfMetadataExtractor` applies the same xref-stream row decoding rule so metadata-side object selection also fails closed on malformed current compressed rows.

The focused fixture builds a previous valid xref table with direct stale page object `4 0 R`, then appends a latest xref stream with `/Prev`, `/Index [4 1]`, `/W [1 0 1]`, and a type-2 row for object 4. Before the fix, the malformed type-2 row was discarded and the previous direct page text leaked. After the fix, only the current guard page imports.

## Verification

Red-first before source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamZeroWidthCarrierCurrentBaseTest.php`

Result: `1 test files / 1 assertions / 1 failure`; stale `Stale zero-width carrier page leak` text was imported.

Focused after source edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStreamZeroWidthCarrierCurrentBaseTest.php`

Result: `1 test files / 24 assertions / 0 failures`.

Adjacent xref object-stream family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfXrefObjectStream*.php lanes/markerpdf/tests/PdfXrefStreamObjectOwner*.php lanes/markerpdf/tests/PdfParserXrefObjectStreamFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserXrefStreamNestedHelperObjectStreamCurrentBaseTest.php`

Result: `36 test files / 707 assertions / 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-xref-object-stream-zero-width-carrier-currentbase.php`

Result: emits `stale_direct_page_suppressed=true`, `zero_width_object_stream_entry_count=1`, `unresolved_object_stream_carrier_count=1`, `object_stream_field_is_zero_width=true`, `invalid_object_stream_carrier_rejected=true`, `object_stream_owner_policy=missing_object_stream_carrier`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-width member-index recovery, duplicate header object-number rejection, duplicate member offsets, invalid later member offsets, object-stream generation repair, current/free carrier repair, object-stream filter operand recovery, xref-stream owner-cycle rejection, hybrid free-entry precedence, `/Prev` object-stream generation storage checks, or classic xref rebuild work. The bounded behavior here is only the xref-stream type-2 object-stream-number field being zero-width/defaulted to invalid carrier `0`.

## Dependency Closure

No new support component is needed. This reuses the native direct-object scanner, xref table and xref-stream `/Prev` chain merger, Flate stream decoder, object-stream member table parser, metadata/text extractors, and WordPress smoke renderer. Full upstream markerPDF parity remains dependency-gated by live `pdftext`, `pypdfium2`/PDFium, Surya/Torch/model downloads, tabled-pdf, Texify, Streamlit/FastAPI runtimes, benchmark/model workflow tooling, and external OCR/rendering helpers; none were executed here.
