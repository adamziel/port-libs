# Inline Image ASCIIHex Post-EOD Boundary Current Base

Session: `port-dev-markerpdf-inline-image-decode-20260606T111628Z`
Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T111628Z`
Base accepted HEAD: `f3e6ef9e9a7803edbdb9db6d76cbe13ebbfcd147`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text extraction before image rendering/OCR/model stages. In the native PHP no-GPU port, `BI ... ID ... EI` inline image bytes are image-owned and must not become WordPress paragraph text, even when a native filter has already reached its own in-band EOD marker.

For `/ASCIIHexDecode`, `>` ends the encoded byte stream. Bytes after `>` but before the real inline-image `EI` are still inside the inline image payload from the content tokenizer's point of view. Non-comment post-EOD surplus that contains delimiter-looking `EI` must therefore stay image-owned until the later actual terminator.

## Implementation

`PdfTextExtractor::inlineAsciiHexCandidateReachesSampleFloorBeforeEod()` now checks the raw candidate segment after the ASCIIHex `>` marker:

- whitespace after `>` is allowed;
- bounded PDF comments after `>` are allowed;
- non-comment surplus must contain a prior delimiter-looking `EI` before the current candidate can close the inline image.

This prevents the first fake `EI` inside ASCIIHex post-EOD surplus from reopening text parsing while preserving clean odd-nibble ASCIIHex decoding and existing comment-after-EOD behavior.

## Red-First Evidence

Before the source edit, this current-base probe:

```text
BI /W 1 /H 1 /CS /G /BPC 8 /F /AHx /D [0 1] ID F>ZZ EI BT ... (ASCIIHex Post EOD Inline Noise) ... EI
```

returned visible lines:

```text
Before AHx Post EOD Inline
ASCIIHex Post EOD Inline Noise
After AHx Post EOD Inline
```

After the source edit, the focused test preserves only:

```text
Before AHx Post EOD Inline
After AHx Post EOD Inline
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 773 assertions, 0 failures`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

Result: emitted `visible_text_imported=true`, `asciihex_post_eod_surplus_payload_excluded_until_real_ei=true`, `asciihex_post_eod_surplus_preview_rejected=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted ASCII85 post-EOD surplus handling, ASCIIHex surplus before EOD, ASCIIHex comment-after-EOD behavior, null filter slots, malformed or duplicate Decode operands, invalid Flate EarlyChange DecodeParms, LZW/RunLength post-EOD boundaries, wrapped terminal filters, preview-only JPX/JBIG2/DCT/CCITT handling, ImageMask/Indexed preview rows, or Image XObject review behavior.

The bounded behavior is specifically ASCIIHex post-EOD non-comment surplus containing fake `EI` bytes before the real inline-image terminator.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF content tokenizer, ASCIIHex stream decoder, inline image dictionary parser, image preview fail-closed path, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL raster parity, external PDF tools, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
