# DCTDecode Comment Terminator Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T203657Z`

Accepted base: `0bc290eeb017fdb8c2af2f4b8348ee2d34f26aa7`

## Source truth

- Upstream markerPDF keeps PDF text extraction and image rendering as separate handoffs (`marker.pdf.extract_text.get_text_blocks` and `marker.pdf.images.render_image`).
- In native PDF parsing, `%` comments are whitespace between PDF tokens. After a DCTDecode/JPEG EOI candidate, a PDF comment before `endstream` must not make the scanner choose an earlier stale `/Length` false-EOI fake `endstream/endobj` decoy.
- JPEG/DCT raster decoding remains preview-only in this no-GPU/no-model lane; the fix is parser boundary recovery only.

## Red-first probe

Before the source edit, a stale `/Length` ending at a false early `\xff\xd9` followed by fake `endstream/endobj`, fake object text, then a true JPEG EOI plus `% comment` before real `endstream` leaked the fake object text:

```text
array (
  0 => 'Before DCT comment false eoi',
  1 => 'DCT comment false EOI leak',
  2 => 'After DCT comment false eoi',
)
Before DCT comment false eoi
DCT comment false EOI leak
After DCT comment false eoi
```

## Implementation

- `PdfTextExtractor::rawDctPreviewEndstreamTerminatorOffset()` now uses a DCT terminator-padding helper that skips PDF comments before checking for `endstream`.
- `PdfImageRenderer::rawDctPreviewStreamTerminatorOffset()` uses the same boundary rule for direct renderer image streams.
- Existing JPEG completeness and review-byte clipping helpers continue to use DCT padding only, so comments after EOI do not become JPEG payload semantics.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-comment-terminator-boundary-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php` => 1 test files, 605 assertions, 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php` => 2 test files, 1121 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-comment-terminator-boundary-currentbase.php` => emitted two Gutenberg paragraphs and metadata with `false_eoi_stale_length_rejected=true`, `pdf_comment_before_real_endstream_skipped=true`, `dctdecode_image_payload_excluded_from_text=true`, and `renderer_recovered_past_stale_length=true`.

## Non-overlap

This avoids prior DCTDecode slices for plain false EOI boundaries, missing-Length malformed false EOI, NUL padding after EOI, tight `endstream` token rejection, post-EOI surplus clipping, prefix-filter EOD boundaries, Crypt Identity, malformed filter operands, SOS marker review, and post-DCT filter boundaries. The new owned edge is only a PDF `%` comment between the true recovered JPEG EOI and the real `endstream` token.

## Dependency closure

No new support component is needed. The patch reuses the existing native PDF token/comment scanner and DCT/JPEG preview boundary helpers. No Python, pdftext, pypdfium/PDFium, PIL, Poppler, Ghostscript, OCR/model, GPU, or external PDF tool execution is introduced.

Root harness: not run - isolated micro-slice.
