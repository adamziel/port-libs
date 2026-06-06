# Inline Image Duplicate Decode Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T052004Z`
Base: `23932dd761e9b54b9c5be6a67898bcd0727918e3`

## Source Truth

MarkerPDF no-GPU scope keeps searchable PDF text extraction native and treats image payloads as media/review metadata rather than paragraph text. This slice maps that boundary for PDF inline images: duplicate top-level `/Decode` declarations, including mixed abbreviated `/D` and full `/Decode` operands after inline dictionary abbreviation expansion, are ambiguous image decode metadata and should fail closed before RGB or ImageMask preview rows.

This is non-overlapping with accepted inline image filter boundary slices for ASCII85, ASCIIHex, Flate predictors, LZW, RunLength, DCT, JPX/JBIG2/CCITT preview-only filters, null filters, unsupported filters, and malformed/unresolved single `/Decode` operands. This slice owns only duplicate inline `/Decode` declaration handling.

## Red-First Evidence

Before the source edit, a duplicate inline `/D` probe used the first declaration and rendered preview rows:

`php -r 'require "tools/bootstrap.php"; ... duplicate inline /D probe ...'`

Result:

`source=explicit`, `component_mismatch=false`, `review_only=false`, `preview_pixel_count=1`.

## Implementation

`PdfImageRenderer::imageDecodeDetails()` now checks duplicate top-level `Decode` declarations before reading the first value. Duplicate declarations return decode metadata with `source=duplicate`, zero component ranges, and `valid_for_components=false`, which drives existing inline review-only and preview rejection paths.

## Verification

Focused inline decode boundary:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 661 assertions, 0 failures`.

Adjacent inline/image renderer family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`

Result: `9 test files, 1810 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php > /tmp/markerpdf-inline-image-decode-boundary-currentbase.html`

Metadata check result:

`visible_text_imported=true`, `duplicate_inline_decode_source="duplicate"`, `duplicate_inline_decode_component_mismatch=true`, `duplicate_inline_decode_review_only=true`, `duplicate_inline_decode_native_raster_decode=false`, `duplicate_inline_decode_preview_rejected=true`, `duplicate_inline_decode_payload_excluded=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP inline image dictionary canonicalization, duplicate dictionary-name scanner, decode metadata planner, and preview-row fail-closed paths. GPU/OCR/Surya/Texify/Torch/model execution and external PDF tools remain intentionally out of scope.
