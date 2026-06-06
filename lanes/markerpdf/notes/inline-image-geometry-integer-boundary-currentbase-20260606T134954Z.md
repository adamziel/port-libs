# Inline Image Geometry Integer Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T134954Z`

Base accepted HEAD: `380f73bd6771c85383ad351d5e11064bf53f0c34`

## Source Truth

- PDF inline images carry `/W`, `/H`, `/BPC`, `/Decode`, `/Filter`, and `/DecodeParms` operands inside page content streams. The native no-GPU markerPDF path must decide the `BI ... ID ... EI` payload ownership boundary before tokenizing page text, and must not let image payload bytes become Gutenberg paragraphs.
- Upstream markerPDF delegates raster/image parsing to PDF/image dependencies, but under the current no-GPU/no-external-tools scope this lane keeps inline image payloads review-only unless bounded native filters and image geometry are safe.
- The accepted base already covered inline image filter terminators, Decode arrays, invalid DecodeParms, preview-only JPX/JBIG2/DCT/CCITT boundaries, and tokenizer fallback. The missing boundary here was unsafe direct inline image geometry integers.

## Behavior

`PdfTextExtractor` now bounds inline image direct integer operands before deriving sample floors. Overlarge `/W`, `/H`, and `/BPC` tokens return `null` instead of being cast to saturated PHP integers, and sample-floor multiplication uses checked positive products before `intdiv()`.

`PdfImageRenderer` now marks invalid inline image geometry operands as review-only metadata and rejects native RGB/ImageMask/Indexed/JPX preview entry points before sample rows are built, including representable `/BPC` values above the native packed-sample preview limit. It also keeps public `image_stream.filter_details` scoped to inline streams with actual DecodeParms metadata, preserving the existing exact public metadata shape for other image previews.

The WordPress smoke proves safe text before/after the overlarge inline image is preserved while payload text such as `Overlarge Geometry Inline Noise` and `abc EI` is excluded.

## Evidence

Pre-implementation probe on accepted base:

- `php -r ...` with `/W 9...000 /H 1 /CS /G /BPC 8` fataled in `PdfTextExtractor::inlineImageExpectedDecodedLength()` because overflowed sample-floor multiplication reached `intdiv()` as a float.

Focused after fix:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` -> `1 test files, 812 assertions, 0 failures`.

Adjacent inline/image family:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php` -> `12 test files, 2074 assertions, 0 failures`.

WordPress smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php > /tmp/markerpdf-inline-image-decode-boundary.html`
- Smoke metadata reports `overlarge_inline_geometry_payload_excluded=true`, `overlarge_inline_geometry_review_only=true`, `overlarge_inline_geometry_preview_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted inline image Decode arrays, invalid DecodeParms, filter-array abbreviation/null-entry boundaries, DCT/JPX/JBIG2/CCITT preview-only filter payload ownership, same-line tokenizer fallback, PageLabels integer overflow, or image XObject stream DecodeParms slices. The new boundary is specifically direct inline image geometry integer operands before native text extraction and RGB preview sample math.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP PDF object scanner, content tokenizer, stream decoder, inline image review planner, and WordPress smoke harness. OCR, Surya/Texify/Torch/model execution, pypdfium/PIL raster execution, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF slice.
