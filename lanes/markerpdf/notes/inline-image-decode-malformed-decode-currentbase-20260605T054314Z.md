# markerPDF Inline Image Malformed Decode Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T054314Z`

Session: `port-dev-markerpdf-inline-image-decode-20260605T054314Z`

Base accepted HEAD: `e0ea57bf3e21d0fc119155f3a11338ab3897fe53`

## Source Truth

Upstream markerPDF routes searchable PDF text through the PDF parser/PDFium text boundary and routes image bytes through image rendering, so inline `BI ... ID ... EI` payloads are not WordPress paragraph text. In the no-GPU native PHP lane, inline image preview metadata may map samples through PDF `/Decode`, but explicit malformed or unresolved `/Decode` operands cannot safely produce RGB or alpha preview rows.

## Behavior

This patch keeps the existing review metadata behavior and adds a fail-closed preview boundary for inline images:

- `PdfImageRenderer::inlineImageReviewPlan()` still records `image_decode_component_mismatch` for explicit malformed, unresolved, or component-mismatched inline `/Decode` operands.
- Inline Indexed, generic inline color-space output, inline ImageMask, and inline JPX ColorKey supplied-sample preview paths now reject those invalid `/Decode` plans before producing preview rows.
- `PdfTextExtractor` continues to exclude malformed inline image payload bytes from visible WordPress text.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
FAIL fails closed on malformed inline image Decode operands before RGB preview rows (lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php)
Expected exception InvalidArgumentException was not thrown

1 test files, 185 assertions, 1 failures
```

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS fails closed on malformed inline image Decode operands before RGB preview rows

1 test files, 188 assertions, 0 failures
```

Adjacent inline/image gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 1091 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke emits `malformed_inline_decode_component_mismatch=true`, `malformed_inline_decode_preview_rejected=true`, `unresolved_inline_decode_preview_rejected=true`, `malformed_inline_imagemask_decode_preview_rejected=true`, `malformed_inline_jpx_colorkey_decode_preview_rejected=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` preamble recovery, unfiltered inline image sample-length `EI` validation, terminal whitespace sample floors, ASCII85/Flate/LZW/RunLength DecodeParms and EOD validation, filtered sample-floor acceptance, indirect valid inline preview operand resolution, inline ImageMask valid decode rows, inline JPX/DCT/JBIG2/CCITT preview-only tokenizer framing, inline Indexed palette/soft-mask previews, malformed inline filter operand fail-closed behavior, object-stream inline image repair, image XObject payload exclusion, DCT CMYK Decode review, or generic XObject image decode component-mismatch metadata.

The bounded behavior is specifically explicit malformed, unresolved, or component-mismatched inline image `/Decode` operands failing closed before RGB/ImageMask/JPX supplied-sample preview rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP inline image dictionary expander, image color-space and Decode planner, content tokenizer, packed-sample preview helpers, `PdfTextExtractor`, `PdfImageRenderer`, and existing WordPress smoke path. Full live raster/OCR/model parity remains out of scope under the no-GPU markerPDF directive and remains gated on pdftext/PDFium/pypdfium2/PIL, Surya/Torch, tabled-pdf, Texify, runtime app/server workers, benchmark/model downloads, and external OCR/rendering helpers.
