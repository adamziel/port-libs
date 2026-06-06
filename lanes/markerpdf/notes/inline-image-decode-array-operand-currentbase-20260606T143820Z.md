# markerPDF Inline Image Decode Array Operand Boundary Current Base

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T143820Z`

Base accepted HEAD: `a077e5887d2a7522aabaf51b3ab7486cab2043dd`

## Source Truth

Upstream `sddai/markerPDF` routes image rendering through parser/PDFium/PIL image paths while searchable text extraction keeps inline `BI ... ID ... EI` image bytes out of text before OCR/model fallback. Under the current no-GPU PHP scope, inline image `/Decode` metadata is review/preview input only, but it must follow PDF object semantics: array entries may be indirect numeric objects, and unresolved or cyclic entries must fail closed.

## Behavior

`PdfImageRenderer` now resolves indirect numeric members inside direct image `/Decode` arrays before building Decode ranges. This applies to inline Indexed image preview rows, DeviceGray output preview rows, and ImageMask stencil preview rows through the shared image Decode parser.

The parser still rejects unresolved, cyclic, non-numeric, duplicate, or component-mismatched Decode arrays before native RGB/stencil preview. Inline image payload bytes remain excluded from WordPress paragraph text.

## Red First

Before the source edit, the focused test failed because `/D [101 0 R 102 0 R]` was treated as malformed instead of resolving to `[0 3]` or `[1 0]`:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL resolves direct inline Decode array indirect numeric operands before RGB preview
Inline Indexed image Decode array must match the image component count before RGB preview.
FAIL resolves direct inline ImageMask Decode array indirect numeric operands before stencil preview
Inline ImageMask Decode array must match the image component count before RGB preview.

1 test files, 0 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS resolves direct inline Decode array indirect numeric operands before RGB preview
PASS resolves direct inline ImageMask Decode array indirect numeric operands before stencil preview

1 test files, 29 assertions, 0 failures
```

Adjacent inline image renderer/decode family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1444 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-array-operand-currentbase.php
```

The smoke emits `direct_decode_array_member_references_resolved=true`, `generation_operands_not_counted_as_decode_values=true`, `indexed_palette_indexes=[0,2,3]`, `gray_decode_inverted=true`, `mask_decode_inverted=true`, `cyclic_decode_member_failed_closed=true`, `inline_payload_excluded_from_text=true`, and all Python/model/PDFium/PIL/external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline image sample-floor tokenization, native filter EOD surplus handling, invalid DecodeParms fail-closed behavior, null filters, indirect geometry operands, whole indirect `/D 104 0 R` arrays, comments/literal/hex decoys inside Decode arrays, duplicate Decode declarations, malformed Decode operand rejection, Image XObject Decode metadata, soft-mask Decode handling, CMap/filter boundaries, OCR/model execution, or external raster rendering.

The bounded behavior is specifically direct inline image `/Decode` arrays whose numeric entries are indirect object references.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF value reader, indirect object resolver, image Decode range builder, inline image preview planners, text extractor, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rasterization, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
