# markerPDF inline image LZW post-EOD boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T103810Z`

Accepted base: `7f71cfc6116b03249ff3e806369e892ec5de9b31`

## Source Truth

Upstream markerPDF separates searchable PDF text extraction from raster image rendering. Under the current no-GPU scope, the native PHP parser must keep inline image bytes out of WordPress paragraphs while exposing review-only image/filter metadata.

PDF LZW streams terminate at the LZW EOD code. For inline images, delimiter-looking `EI` bytes in malformed post-EOD surplus must not reopen content parsing before the parser reaches a defensible inline-image boundary.

## Implementation

`PdfTextExtractor` now parses LZW codes through EOD when validating bounded inline-image filter end markers. A fake `EI` after non-whitespace LZW post-EOD surplus no longer closes the inline image early; recovery only accepts a later marker after seeing delimiter-looking surplus, matching the existing malformed ASCIIHex surplus boundary shape.

`PdfImageRenderer` now applies the same LZW EOD boundary for inline preview decoding, so post-EOD surplus is rejected before RGB preview rows instead of being counted as native raster data.

## Red Probe

Before the parser fix, the new focused case failed:

```text
FAIL requires bounded LZW EOD before accepting inline image EI terminators
Actual text lines included: LZW Post EOD Inline Noise
```

After adding the first bounded-EOD check, the fake `EI` was rejected but the parser swallowed the later visible paragraph. The final patch adds the bounded post-EOD recovery path and keeps the visible text after the true inline-image terminator.

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
1 test files, 312 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke exits 0 and emits:

- `lzw_post_eod_surplus_payload_excluded_until_real_ei=true`
- `lzw_post_eod_surplus_preview_rejected=true`
- `inline_filter_post_eod_surplus_preview_rejected=true`
- `excluded_inline_image_text=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed `BI` recovery, unfiltered sample-length validation, ASCII85/ASCIIHex/RunLength EOD boundaries, Flate DecodeParms inline validation, LZW DecodeParms success preview rows, DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, unsupported `/Crypt` handling, inline ImageMask or Indexed palette preview rows, object-stream inline-image repair, or image XObject payload exclusion.

The bounded behavior is specifically LZW-filtered inline images whose decoded data reaches EOD before malformed non-whitespace surplus containing fake `EI` bytes.

## Dependency Closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, LZW decoder, DecodeParms reader, image preview filter pipeline, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL raster rendering, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
