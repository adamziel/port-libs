# markerPDF Inline Image Malformed Filter Preview Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T043434Z`

Base accepted HEAD: `e50f09220eaa5f3cade103838843b7f3c365e963`

## Source Truth

Upstream `sddai/markerPDF` routes searchable PDF text through parser-backed PDF extraction before image rendering and OCR/model fallbacks. Inline `BI ... ID ... EI` bytes are image payload, not WordPress paragraph text, and image rendering proceeds only after the filter boundary is understood.

The native PHP text extractor already fails closed for malformed or unresolved inline `/Filter` operands. This slice carries the same boundary into `PdfImageRenderer` preview paths so malformed `/Filter` values cannot be silently treated as an unfiltered sample stream.

## Red First

Before the source change, a focused probe treated malformed and unresolved filter operands as no filter and decoded raw samples:

```text
php -r 'require "tools/bootstrap.php"; $r=new PortLibs\MarkerPDF\PdfImageRenderer(); foreach (["/W 3 /H 1 /CS /G /BPC 8 /F [ << /Bad true >> ] /D [0 1]"=>"ABC", "/W 3 /H 1 /CS /G /BPC 8 /F 99 0 R /D [0 1]"=>"ABC"] as $dict=>$payload) { try { $p=$r->inlineImageColorSpaceMaskOutputPreviewRows($dict, $payload, [], 3); var_export([$p["image_stream"], $p["preview_pixel_count"], array_column($p["pixels"], "decoded_gray")]); echo "\n"; } catch (Throwable $e) { echo get_class($e).": ".$e->getMessage()."\n"; } }'
```

Both cases returned `filters => []`, `decoded_with_current_filters => true`, and three raw preview pixels. That was unsafe because the renderer could promote undecodable inline image bytes to output preview rows.

## Implementation

`PdfImageRenderer::imageFilterValues()` now preserves invalid `/Filter` operands as explicit `MalformedFilterOperand` or `UnresolvedFilterOperand` sentinels instead of dropping them. The existing native filter decoder then reports a decode failure, causing RGB, Indexed, and ImageMask preview paths to throw before output rows are produced.

`imageColorSpaceSoftMaskPlan()` and `inlineImageReviewPlan()` mark these operand boundaries as non-native raster decode and add review notes:

- `malformed_image_filter_operand_fail_closed`
- `unresolved_image_filter_operand_fail_closed`
- `inline_malformed_image_filter_operand_fail_closed`
- `inline_unresolved_image_filter_operand_fail_closed`

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS fails closed on malformed inline image filter operands before output previews
PASS fails closed on unresolved inline image filter references before indexed previews

1 test files, 25 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-malformed-filter-preview-currentbase.php
```

The smoke exits `0` and emits `malformed_inline_filter_preview_failed_closed=true`, `unresolved_inline_filter_preview_failed_closed=true`, `malformed_native_raster_decode=false`, `unresolved_native_raster_decode=false`, `inline_payload_excluded_from_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the queued unsupported `/Crypt` inline filter handoff, DCT/JPX/JBIG2/CCITT review-only raster boundaries, null filter array alignment, malformed inline-filter text extraction, RunLength EOD, LZW, ASCII85, Flate DecodeParms, JPX segment boundary, named ColorSpace tokenizer handling, object-stream inline-image repair, Image XObject payload exclusion, or standalone stream-filter fail-closed behavior.

The bounded behavior is specifically renderer-side fail-closed handling for malformed and unresolved inline image `/Filter` operands before output previews.

## Dependency Closure

No new support component is needed. This reuses the native PHP inline image dictionary parser, current object resolver, image filter stack metadata, decoder fail-closed path, `PdfTextExtractor`, focused tests, and the WordPress smoke path. Full OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive and remains gated on external model/runtime/image-rendering components.
