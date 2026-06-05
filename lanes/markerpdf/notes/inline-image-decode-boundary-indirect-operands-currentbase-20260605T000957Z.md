# markerPDF Inline Image Decode Boundary Indirect Operands Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T000957Z`

Base accepted HEAD: `e5899325ac444d1e8dee1e7e7294afaa8b7e8ca7`

## Source Truth

markerPDF delegates searchable PDF extraction and image preview boundaries to parser-backed PDF utilities before OCR/model fallback. In the native no-GPU PHP port, the equivalent boundary is that inline image dictionaries are expanded into XObject-compatible dictionaries, payload bytes stay out of visible text, and preview metadata is computed only from the current parser object map.

This slice covers a robustness boundary already implied by the lane's inline image tokenizer: current-object integer operands read from an inline image dictionary must be resolved before bounded RGB or stencil preview. Without that, an operand such as `/W 101 0 R` can be misread as width `101` instead of resolving object `101` to width `3`.

## Change

- `PdfImageRenderer` now resolves object-map integer operands for image `/BitsPerComponent` and `integerNameValue()` callers that pass an object map.
- Inline Indexed, inline JPX ColorKey, inline color-space/mask output preview, and inline ImageMask stencil preview now resolve `/Width` and `/Height` through the provided current object map.
- ImageMask details now resolve width/height and bits before reporting stencil preview metadata.
- The existing WordPress smoke now reports indirect inline preview operand resolution and indirect ImageMask geometry resolution.

## Red First

A one-off PHP probe before the source edit exercised:

`/W 101 0 R /H 102 0 R /CS [/I /RGB 3 91 0 R] /BPC 103 0 R /F [/AHx /Fl] /D 104 0 R`

with objects `101 => 3`, `102 => 1`, `103 => 2`, and `104 => [0 3]`. The current base failed with:

`InvalidArgumentException: Image sample packing parameters are invalid.`

The failure was caused by treating object numbers as image geometry and sample bit depth.

## Focused Evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result:

`1 test files, 57 assertions, 0 failures`

This adds 2 focused PASS cases and 28 assertions over the pre-slice focused file count of 29 assertions.

Adjacent gate:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`

Result:

`8 test files, 822 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`

Observed metadata included:

- `visible_text_imported=true`
- `resolves_current_indirect_inline_preview_operands=true`
- `indirect_inline_palette_indexes=[0,1,3]`
- `resolves_current_indirect_inline_imagemask_geometry=true`
- `indirect_inline_imagemask_opacity=[0,1,0,1]`
- `excluded_inline_image_text=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Syntax and whitespace:

- `php -l lanes/markerpdf/src/PdfImageRenderer.php` - passed
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` - passed
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` - passed
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` - passed
- `git diff --check -- lanes/markerpdf` - passed

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, ASCII85/Flate DecodeParms delimiter validation, inline JPX/DCT/JBIG2/CCITT tokenizer framing, inline ImageMask direct preview rows, inline Indexed/JBIG2 review metadata, inline filter-array abbreviation/null-entry handling, JPX/soft-mask preview, or image XObject filter exclusion. The new behavior is specifically current-object integer operand resolution for inline image preview geometry and decode metadata before bounded RGB/stencil review rows.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP inline image dictionary expander, PDF value resolver, image stream filter decoder, packed-sample reader, Decode mapper, and WordPress smoke path. Full upstream OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive.
