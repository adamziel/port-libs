# markerPDF Inline Image Decode Boundary LZW Current Base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T011534Z`

Session: `port-dev-markerpdf-inline-image-decode-20260605T011534Z`

Base accepted HEAD: `f7caf97e74b9a295178c583a8880c2c4561d8c2d`

## Source Truth

Upstream markerPDF routes searchable PDF extraction and image handling through parser-backed PDF utilities before OCR/model fallback. In the native no-GPU PHP port, the equivalent boundary is that `BI ... ID ... EI` inline image bytes are image payloads, not WordPress paragraph text, and bounded image-preview metadata should use the same native stream-filter semantics already trusted by text-token boundary validation.

PDF inline image dictionaries permit short keys and values such as `/F /LZW`, `/DP`, `/D`, `/W`, `/H`, `/CS`, and `/BPC`. LZWDecode is a standard stream filter; `/DecodeParms` can carry `Predictor`, `Columns`, `Colors`, `BitsPerComponent`, and LZW `EarlyChange`. Malformed integer DecodeParms must fail closed before previewing image samples.

## Behavior

Before this patch, `PdfTextExtractor` could use LZWDecode and DecodeParms while validating inline image text-token boundaries, but `PdfImageRenderer::inlineIndexedImageStreamPreviewRows()` treated `/LZWDecode` as a failed native image-preview filter. That meant WordPress review metadata could not preview an inline Indexed image whose payload used LZW plus TIFF predictor rows, even though the same filter family was already native elsewhere in the lane.

After this patch:

- `PdfImageRenderer` decodes `/LZWDecode` and `/LZW` in the same image stream-filter dispatcher used by inline Indexed and ImageMask previews.
- LZW `EarlyChange` is honored while reading variable-width codes.
- DecodeParms predictors are allowed for Flate and LZW only.
- Malformed or unresolved integer DecodeParms, including invalid LZW `EarlyChange`, fail closed before RGB preview.
- The WordPress smoke reports `lzw_inline_decodeparms_preview_decoded=true`, `lzw_inline_palette_indexes=[0,1,3]`, and `invalid_lzw_earlychange_decode_failed=true`.

## Red First

With the new focused case added before the source patch:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes
PASS requires ASCII85 inline image review payload terminator before RGB preview decoding
PASS decodes Flate DecodeParms inline image payload before accepting EI boundaries
PASS accepts filtered inline image EI after decoded sample floor is reached
FAIL decodes LZW DecodeParms inline image payload before Indexed RGB preview (lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php)
Inline Indexed image filters must be natively decoded before sample preview.
PASS resolves current indirect inline image decode operands before Indexed RGB preview
PASS resolves current indirect inline ImageMask geometry before stencil preview

1 test files, 65 assertions, 1 failures
```

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS requires ASCII85 inline image end marker before accepting delimiter-looking EI bytes
PASS requires ASCII85 inline image review payload terminator before RGB preview decoding
PASS decodes Flate DecodeParms inline image payload before accepting EI boundaries
PASS accepts filtered inline image EI after decoded sample floor is reached
PASS decodes LZW DecodeParms inline image payload before Indexed RGB preview
PASS resolves current indirect inline image decode operands before Indexed RGB preview
PASS resolves current indirect inline ImageMask geometry before stencil preview

1 test files, 85 assertions, 0 failures
```

Adjacent inline/image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 8 selected test files (root lock skipped)
8 test files, 842 assertions, 0 failures
```

Syntax, JSON, and whitespace:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
No syntax errors detected in lanes/markerpdf/src/PdfImageRenderer.php

php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); json_decode(file_get_contents("lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf json ok\n";'
markerpdf json ok

git diff --check -- lanes/markerpdf
passed with no output
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emitted `lzw_inline_decodeparms_preview_decoded=true`, `lzw_inline_palette_indexes=[0,1,3]`, `invalid_lzw_earlychange_decode_failed=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with six clean Gutenberg paragraph blocks.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, ASCII85 explicit terminator review, Flate DecodeParms delimiter validation, filtered sample-floor acceptance, indirect inline preview operand resolution, inline DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, inline ImageMask preview rows, inline Indexed JBIG2/palette/alpha previews, inline filter-array null alignment, object-stream inline-image repair, image XObject payload exclusion, or standalone text-stream LZWDecode extraction.

The bounded behavior is specifically LZWDecode plus DecodeParms handling in inline image preview rows, including predictor application and invalid `EarlyChange` fail-closed behavior before WordPress RGB review metadata.

## Dependency Closure

No new support component is needed. This reuses the native PHP inline image dictionary expander, current object-map resolver, image stream filter decoder, packed-sample reader, Decode mapper, `PdfImageRenderer`, and WordPress smoke path. Full OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
