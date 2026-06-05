# markerPDF Inline Image Array Color-Space Boundary

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T084905Z`

Base accepted HEAD: `980ef492bfe4c1ebea9d77eeee80c623451a7e76`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed page extraction before image/OCR/model stages. At that boundary, PDF `BI ... ID ... EI` inline image bytes are image payload, not paragraph text. The native no-GPU port should therefore keep inline image payload bytes out of WordPress text even when the inline image uses direct array color spaces instead of simple `/DeviceRGB` names.

## Implementation

`PdfTextExtractor` now uses direct inline color-space array component counts when computing the unfiltered inline-image sample floor before accepting tight `EI` terminators. The tokenizer recognizes `/DeviceN` colorant arrays, `/Separation`, `/CalGray`, `/CalRGB`, `/Lab`, `/ICCBased` `/N`, `/Indexed`, and the simple device spaces already handled by the native image preview path.

The existing WordPress inline-image decode smoke now includes tight false-`EI` bytes inside `/DeviceN` and `/CalRGB` inline image payloads and reports `array_colorspace_component_floor_preserved=true`.

## Red First

Before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses inline array color-space component counts before accepting unfiltered EI boundaries
Expected: Before/After DeviceN and CalRGB lines only
Actual: DeviceN Inline Decode Noise and CalRGB Inline Decode Noise leaked into text
1 test files, 267 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 275 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

The smoke exits 0 and emits `visible_text_imported=true`, `array_colorspace_tight_ei_payloads_present=true`, `array_colorspace_component_floor_preserved=true`, `excluded_inline_image_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline ASCII85/ASCIIHex terminator handling, Flate/LZW/RunLength DecodeParms preview decoding, null filter DecodeParms alignment, DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, terminal whitespace sample handling, named color-space fallback, malformed inline filter/decode fail-closed handling, indirect inline preview operands, ImageMask previews, or Image XObject review behavior.

The bounded behavior is specifically direct array color-space component counting for unfiltered inline image tokenizer sample floors.

## Dependency Closure

No new support component is needed. This reuses the native PDF content tokenizer, inline dictionary abbreviation expansion, existing PDF array parsing helpers, focused lane tests, and the existing WordPress smoke path. Live OCR/model/raster parity remains intentionally out of scope under the no-GPU markerPDF directive.
