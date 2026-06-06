# markerPDF inline image Decode null boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T163225Z`

Base accepted HEAD: `9b626637adac74dd83b40dfa99de8ceeabc8d9b2`

## Source truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering. At the native PHP parser boundary, inline image bytes between `BI ... ID ... EI` are raster payload and `/Decode` metadata only controls image sample interpretation before RGB/stencil preview.

PDF dictionary semantics treat a key whose value is `null` as omitted. This slice maps that boundary for content-stream inline images: direct `/D null` and `/Decode null` no longer mark the image review as malformed, reject preview rows, or promote payload-looking text into WordPress paragraphs.

## Behavior

`PdfImageRenderer::imageDecodeDetails()` now treats resolved `null` Decode operands as absent:

- normal grayscale/RGB inline images use no explicit decode array and remain native-preview eligible;
- ImageMask inline images use the existing default stencil decode `[0 1]`;
- malformed, duplicate, unresolved, and component-mismatched Decode operands remain review-only and fail closed.

The focused fixture includes a grayscale inline image with `/D null` whose payload contains `BT ... Tj` text-looking bytes, plus an ImageMask with `/D null`. WordPress-visible text preserves only the surrounding paragraphs, grayscale preview decodes with default component handling, and ImageMask preview reports default decode metadata.

## Red-First Evidence

Before the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Failed at the new case:

`FAIL treats direct null inline image Decode operands as omitted before preview rows`

Reason: `Inline image Decode array must match the image component count before RGB preview.`

## Verification

After the source change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 827 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`

Result: smoke completed; parsed metadata reports `visible_text_imported=true`, `null_inline_decode_operand_treated_as_omitted=true`, `null_inline_decode_preview_native_raster_decode=true`, `null_inline_imagemask_default_decode_source=default`, `null_inline_decode_payload_excluded=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted inline image tokenizer fallback, same-line graphics prefixes, ASCII85/ASCIIHex/RunLength/LZW/Flate filter EOD ownership, null `/Filter`, null DecodeParms slots, malformed/duplicate/unresolved Decode arrays, overlarge geometry operands, Image XObject soft-mask, OCR/model, or preview-only raster filter surfaces.

The bounded behavior is only the direct inline image `/Decode null` operand boundary before native preview and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP inline image dictionary canonicalizer, image Decode planner, image preview row helpers, content tokenizer, and WordPress smoke harness. Full live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive.
