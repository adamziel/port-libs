# CCITT Fax Indirect Comment Operand Boundary

Session: `port-dev-markerpdf-ccitt-fax-filter-20260608T220417Z`
Micro-slice: `markerpdf-ccitt-fax-filter-boundary-current-base-20260608T220417Z`
Base accepted HEAD: `744d742adbbbf391182231a7a5b4f2d0d558edc2`

## Source Truth

This stays inside the native no-GPU markerPDF scope. PDF comments are token whitespace, so an indirect object body such as `% comment\n/CCF` or `% comment\n<< /K -1 ... >>` must resolve to the underlying filter name or DecodeParms dictionary before CCITT Fax image review. CCITT Fax remains review-only in this PHP lane; no OCR, Surya/Texify/Torch, PDFium/PIL raster execution, multiprocessing, or external PDF tools are invoked.

## Red-First Boundary

Before this patch, the standalone renderer path resolved a comment-prefixed indirect filter object as a malformed operand:

```text
image_filters = ["MalformedFilterOperand"]
ccitt_fax_decode_boundary = null
image_filter_boundary.filter_operand_policy = reject_malformed_filter_operands
```

That meant future renderer-only callers could fail closed before reaching the same PDF-comment whitespace behavior already expected by the broader parser.

## Implemented Behavior

- `PdfImageRenderer::resolvePdfValueWithSeen()` now trims PDF whitespace/comment boundaries before checking an indirect object reference.
- Recursive indirect value resolution now passes the object body through the same PDF boundary trim instead of plain PHP `trim()`.
- Comment-prefixed indirect `/Filter` and `/DecodeParms` values resolve for renderer review, inline image review, and Image XObject review.
- Source aliases such as `/CCF` are preserved in review metadata while canonical CCITT Fax classification stays available through `ccitt_fax_filter_boundary`.
- WordPress visible text extraction still excludes CCITT image payload bytes.

## Verification

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfCcittFaxIndirectCommentOperandBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-comment-currentbase.php
```

All changed PHP files reported no syntax errors.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxIndirectCommentOperandBoundaryCurrentBaseTest.php
```

Result: `1 test files, 32 assertions, 0 failures`.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCcittFaxIndirectCommentOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxIndirectFilterArrayTailCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfCcittFaxDecodeParmsTrailingOperandCurrentBaseTest.php
```

Result: `6 test files, 1317 assertions, 0 failures`.

```text
php lanes/markerpdf/examples/wordpress-pdf-ccitt-fax-indirect-comment-currentbase.php
```

Result: exits 0 with `renderer_comment_filter_resolved=true`, `xobject_comment_filter_resolved=true`, `payload_excluded_from_paragraphs=true`, `native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted CCITT filter array comment handling, escaped filter keys, null filter DecodeParms alignment, trailing DecodeParms operand rejection, generation-exact DecodeParms resolution, indirect filter array tail handling, CCITT EOFB/RTC row boundaries, native prefix handoff, DCT/JPX/JBIG2 image review, or any model/OCR behavior.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF tokenizer/image review pipeline and tightens value-boundary resolution inside `PdfImageRenderer`.
