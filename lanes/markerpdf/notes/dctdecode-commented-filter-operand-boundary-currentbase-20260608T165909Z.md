# DCTDecode Commented Filter Operand Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T165909Z`

Base accepted HEAD: `d95c6bf59d89f3e3e2b403b79e9517c83cdff5a1`

## Source Truth

Upstream markerPDF keeps PDF text extraction and image rendering as parser-backed handoffs before OCR/model stages. In native PDF parsing, `%` comments are whitespace between PDF tokens, but comments also mark an important source boundary: an extra decoder token after `/Filter /DCTDecode` must still fail closed even when the token is separated from the filter value by a comment.

This no-GPU slice keeps DCTDecode/JPEG payloads review-only and preserves that the malformed extra filter operand occurred after a PDF comment in both image XObject review metadata and the direct `PdfImageRenderer` plan.

## Implementation

- `PdfTextExtractor` now carries `extra_filter_operand_after_comment=true` into image XObject filter-boundary metadata when a malformed DCT filter operand follows a PDF comment.
- `PdfImageRenderer` now records the same metadata on `image_filter_boundary` for direct renderer and WordPress review paths.
- DCT raster bytes remain excluded from visible text and native raster decode.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeCommentedFilterOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS preserves comment-separated extra DCTDecode Filter operands before WordPress image review

1 test files, 44 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-commented-filter-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `stream_filters=["MalformedFilterOperand","DCTDecode"]`, `preview_only_filters=["DCTDecode"]`, `filter_operand_policy="reject_malformed_filter_operands"`, `extra_filter_operand_after_comment=true`, recovered XObject/renderer raw lengths, `dctdecode_payload_excluded_from_text=true`, and all Python/model/PDF-tool flags false.

## Non-Overlap

This does not repeat accepted direct DCT stream exclusion, DCT APP/SOS marker parsing, raw comment-before-`endstream` recovery, DCT alias/escaped filter names, null-filter DecodeParms slots, ASCII85/ASCIIHex/LZW/RunLength/Flate prefix-filter DCT boundaries, Crypt Identity, duplicate filters, generic malformed array/scalar filter operand counts, post-DCT filter review, inline DCT boundaries, CCITTFax/JPX/JBIG2 preview-only filters, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically preserving the PDF-comment source boundary for a malformed extra DCTDecode `/Filter` operand while keeping JPEG bytes out of WordPress text and native raster decode.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary scanner, comment-aware token boundary helpers, DCT/JPEG review-only image handoff, `PdfTextExtractor`, `PdfImageRenderer`, and the WordPress smoke path. Full JPEG raster parity, OCR/model execution, pypdfium/PDFium, PIL, Poppler, Ghostscript, and GPU work remain intentionally out of scope.

Root harness: not run - isolated micro-slice.
