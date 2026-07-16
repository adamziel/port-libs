# Inline Image Tokenizer DeviceN Tint Boundary

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260607T022758Z`
Base: `dceb129b94af76d8e90cb1d4f15360a8db272ff2`

## Behavior

This patch keeps the native searchable-PDF text tokenizer from treating a later stray `EI` operator as part of a preview-only inline image fallback when valid high-component DeviceN tint operands appear immediately after the real inline image terminator.

The red fixture uses a 10-colorant `/DeviceN` color space:

```pdf
BI /W 8 /H 1 /IM true /F /JBIG2Decode ID
\x80 EI
/CS10 cs
0.1 0.2 0.3 0.4 0.5 0.6 0.7 0.8 0.9 1.0 scn
BT ... (Visible DeviceN Tint Before Stray) Tj ET
EI
BT ... (Visible After DeviceN Tint Stray) Tj ET
```

Before the source change, the fallback validator rejected the tenth numeric color operand before it could classify the `scn` operator, so `Visible DeviceN Tint Before Stray` was swallowed. `PdfTextExtractor` now uses the same bounded color-operand limit while queuing numeric graphics operands and while validating `SC`/`sc`/`SCN`/`scn`, preserving valid high-component tint state without accepting unbounded operand runs.

## Evidence

Red-first direct focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 696 assertions, 1 failures
```

Focused after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
1 test files, 707 assertions, 0 failures
```

Adjacent inline-image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
13 test files, 2389 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
preview_only_devicen_tint_stray_ei_text_preserved_after_safe_boundary=true
```

## Non-Overlap

This is not another inline `/Decode`, native-filter EOD comment, DCT/JPX/CCITT, tight ID/EI, pattern tint, shading, dash, text-state, compatibility-scope, Type3 metric, or ActualText boundary. It only widens the already bounded graphics color operand validation enough for high-component DeviceN tint state after a preview-only inline image fallback.

## Dependency Closure

No new support component is needed. The change reuses the native PHP content tokenizer and existing searchable-PDF text extraction path. GPU/model/OCR execution, PDFium raster parity, Surya/Texify/Torch, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF lane.
