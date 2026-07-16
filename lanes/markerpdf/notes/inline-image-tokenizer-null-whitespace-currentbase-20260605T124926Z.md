# markerpdf-inline-image-tokenizer-boundary-current-base-20260605T124926Z

Accepted base: `c1cf1f37714011b48942dddb280e21fdc933c11e`

Scope: native no-GPU markerPDF PDF content tokenizer behavior. PDF lexical whitespace includes NUL (`0x00`), so inline image `BI`, dictionary operands, `ID`, raster bytes, and `EI` boundaries separated by NUL bytes must remain inline-image payload, not visible WordPress text.

Implementation:

- `PdfTextExtractor` now routes the content-tokenizer and inline-image boundary helpers through `isPdfWhitespace()`, which treats `0x00` as whitespace in addition to the existing `ctype_space()` characters.
- The focused fixture covers `BI<NUL>/W ... ID<NUL>` and `payload<NUL>EI<NUL>BT ...`, with a fake `BT ... Tj` text object inside the inline image bytes. The payload text stays hidden while the paragraph after the image survives.
- The existing WordPress smoke now emits `pdf_null_whitespace_inline_payload_excluded=true`.

Red/green evidence:

- Red before production change: `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` -> `1 test files / 257 assertions / 1 failure`, leaking `NUL Inline Payload Noise`.
- Green after implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` -> `1 test files / 268 assertions / 0 failures`.
- Smoke: `php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php | rg "pdf_null_whitespace|markerpdf-inline-image"` -> metadata marker includes `pdf_null_whitespace_inline_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Dependency closure:

No new support component is needed. This reuses the existing native PHP PDF content tokenizer and inline-image boundary scanner. GPU/model OCR, raster execution, PDF action execution, and external PDF tooling remain intentionally out of scope for this lane.

Non-overlap:

This does not repeat earlier inline-image cases for tight `ID`, PDF comments after `ID`, tight `EI`, slash-delimited `EI`, preview-only JPX/JBIG2/CCITT filters, or malformed filter preview planning. The new boundary is PDF NUL whitespace in token separators.
