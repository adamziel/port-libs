# Inline Image Preview Prefix Decode Boundary - 2026-06-05

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T092727Z`

Accepted base: `922147e2c9750d289d5a7a1ba0a7ed8e2673e9d3`

## Source Truth

Upstream markerPDF routes searchable-PDF text through parser/PDF text extraction before image/OCR/model stages. PDF inline `BI ... ID ... EI` image bytes are raster payload, not visible WordPress paragraph text. For native no-GPU import fidelity, PHP needs to preserve inline image filter boundaries and review metadata without claiming JPX/JBIG2/DCT raster decode.

## Behavior

This patch keeps preview-only inline filters review-only while preserving the bytes decoded by native prefix filters before the preview handoff. A stack such as `[/AHx /JPXDecode]` now exposes `native_prefix_decoded_length`, `native_prefix_decoded_sha256`, `native_prefix_decoded_preview_hex`, and `stopped_before_filter=JPXDecode` in public image-stream metadata. `decoded_with_current_filters` remains `false` because the JPX raster stage is still not natively decoded.

The supplied-sample JPX/ColorSpace preview paths now also fail closed when a native inline prefix filter is malformed or missing its explicit inline EOD marker. This prevents supplied samples from bypassing an incomplete `ASCIIHexDecode`, `ASCII85Decode`, or `RunLengthDecode` prefix before a preview-only raster filter.

## Red-First Evidence

Before the renderer change:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: failed the new wrapped JPX case because `image_stream.native_prefix_decoded` was absent.

## Verification

Focused:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 289 assertions, 0 failures`

Adjacent inline-image family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php`

Result: `8 test files, 674 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`

Result: emitted `wrapped_jpx_prefix_native_filter_decoded_before_preview_only=true`, `wrapped_jpx_prefix_missing_eod_rejected=true`, `wrapped_jpx_prefix_payload_excluded_from_text=true`, and both model/external-tool flags false.

## Non-Overlap

This does not repeat prior ASCIIHex/ASCII85/RunLength EOD enforcement, DCT tokenizer boundaries, unsupported filter review-only behavior, inline array ColorSpace sample floors, or JPX false-EOC tokenizer handling. The new surface is renderer metadata and supplied-sample fail-closed behavior for native prefix filters before preview-only raster filters.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP stream decoders and the existing supplied-boundary review path. JPX/JBIG2/DCT raster decoding, OCR, Surya/Texify/Torch, and model-worker parity remain intentionally out of scope under the no-GPU markerPDF lane rules.
