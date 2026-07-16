# markerPDF Inline Image Decode Review-Only Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T210110Z`

Accepted base: `68e05d76831a99dc0655fe8f9599b7d9f68bfc9f`

## Source Truth

Upstream `sddai/markerPDF` separates searchable PDF text extraction from image rendering and OCR/model paths. Under the current no-GPU markerPDF scope, inline `BI ... ID ... EI` image payloads stay out of WordPress paragraph text, and malformed image decode metadata must fail closed before RGB preview or native-raster claims.

## Behavior

`PdfImageRenderer::inlineImageReviewPlan()` already recorded explicit or unresolved inline `/Decode` operands as `image_decode_component_mismatch`, and preview row builders already rejected them. This slice closes the remaining review-metadata gap: invalid inline `/Decode` operands now also mark the inline image as `inline_image_review_only=true` and `inline_image.native_raster_decode=false`, with an `inline_image_decode_operand_review_only` note.

The text extraction boundary remains unchanged: malformed inline image payload bytes are excluded from visible WordPress text, while the following searchable text imports normally.

## Evidence

Current-base probe before the source edit:

```text
php -r 'require "tools/bootstrap.php"; ... inlineImageReviewPlan("/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 1 0 1]", "\x00", [91 => "<000000FF000000FF000000FF>"]);'
array (
  0 => true,
  1 => false,
  2 => true,
  3 => true,
)
```

The tuple is `image_decode_component_mismatch`, `inline_image_review_only`, `inline_image.native_raster_decode`, and `image_filter_boundary.native_raster_decode`, showing the malformed Decode was detected but the inline image still claimed native raster decode.

Focused run after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks invalid inline image Decode operands as review-only before native raster metadata
1 test files, 525 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke metadata includes `malformed_inline_decode_review_only=true`, `malformed_inline_decode_native_raster_decode=false`, `unresolved_inline_decode_review_only=true`, `unresolved_inline_decode_native_raster_decode=false`, `malformed_inline_decode_preview_rejected=true`, and all Python/model/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted inline image payload tokenization, native filter sample floors, ASCII85/ASCIIHex/LZW/RunLength EOD surplus handling, Flate/JPX native-prefix completion, malformed Decode preview rejection, malformed DecodeParms fail-closed filtering, indirect inline operands, inline masks, Indexed palette previews, image XObject boundaries, CMaps, xref repair, OCR/model execution, or external raster tooling.

The bounded behavior is specifically inline image review metadata for malformed or unresolved `/Decode` operands after they are already detected as component-invalid.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP inline image dictionary expander, image Decode planner, image review metadata planner, text tokenizer, and existing WordPress smoke. Live OCR, PDFium/PIL raster execution, Surya/Texify/Torch models, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope.
