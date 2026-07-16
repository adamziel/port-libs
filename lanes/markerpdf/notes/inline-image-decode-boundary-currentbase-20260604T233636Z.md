# markerPDF inline image decode boundary current base

Session: `port-dev-markerpdf-inline-image-decode-20260604T233636Z`
Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260604T233636Z`
Base accepted HEAD: `a5e387c4d1094f3921390a1c90f9966afea84bd2`

## Source truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` via pdftext/PDFium text extraction, while image rendering and RGB conversion stay on the image path. Inline `BI ... ID ... EI` payload bytes are image data, not text spans.

Native PHP source truth for this slice is the same PDF inline-image stream boundary: native preview code may decode supported filters for review, but an inline ASCII85/ASCIIHex image payload must reach its explicit filter terminator before those bytes can be considered complete RGB-preview sample data.

## Implementation

`PdfImageRenderer::decodeImageStreamByFilters()` now accepts a bounded `requireExplicitFilterEndMarkers` flag. Inline preview callers set it when decoding inline payload bytes, so `/A85` and `/AHx` payloads missing `~>` or `>` fail closed instead of being treated as complete sample data. Object/XObject image stream decoding remains length-bounded and unchanged.

The existing `PdfTextExtractor` tokenizer boundary remains intact: page text extraction still rejects delimiter-looking `EI` bytes inside ASCII85 and Flate/DecodeParms inline payloads before WordPress paragraph rendering.

## Red-first evidence

Before the patch, this current-base probe decoded an unterminated ASCII85 inline image review payload:

`PdfImageRenderer::inlineIndexedImageStreamPreviewRows('/W 3 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 2 /F /A85 /D [0 3]', '<~+T', [91 => '<000000FF000000FF000000FF>'], 3)`

It returned `decoded_with_current_filters=true`, `decoded_length=1`, and three preview pixels. After the patch, the focused test requires `InvalidArgumentException` for the same missing-terminator payload while the complete `<~+T~>` payload still decodes to three preview pixels.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  Result: `1 test files, 29 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php`
  Result: `7 test files, 205 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  Result: smoke emitted four Gutenberg paragraphs plus `complete_ascii85_review_decoded=true`, `complete_ascii85_review_preview_pixels=3`, `incomplete_ascii85_review_decode_failed=true`, `requires_ascii85_review_end_marker_before_rgb_preview=true`, and `excluded_inline_image_text=true`.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php`
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  Result: all syntax checks passed.

## Non-overlap

This does not repeat accepted `PdfTextExtractor` ASCII85 `EI` delimiter handling, Flate DecodeParms inline-image payload validation, inline DCT/JPEG EOI validation, inline JPX/JBIG2 preview framing, inline ImageMask preview rows, inline Indexed palette/alpha previews, inline filter-array abbreviation/null-entry alignment, object-stream inline-image repair, or generic image XObject payload exclusion.

The new behavior is specifically `PdfImageRenderer` inline preview decode fail-closed handling for incomplete native ASCII85/ASCIIHex inline payloads before RGB review metadata.

## Dependency closure

No new support component is needed. This reuses the native PHP inline image review path, image filter decoder, and palette/sample preview helpers. Full live raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; scanned-PDF OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope for the no-GPU markerPDF lane.
