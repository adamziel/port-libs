# markerPDF inline image tokenizer shading boundary current base

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T174619Z`

Base accepted HEAD: `165d00972e222ec74a0a4ac65ceaafba6ceef98e`

## Source truth

Upstream `sddai/markerPDF` at manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction through parser-backed pdftext/PDFium before image/OCR/model stages. In this no-GPU PHP lane, bytes between inline-image `BI ... ID ... EI` markers are raster payload, while valid non-text graphics operators after the real `EI` terminator must not hide following searchable text.

This slice covers a fresh tokenizer fallback boundary: a preview-only inline image followed by a valid shading-paint operation `/Shade1 sh`, visible text, then a later stray `EI` operator. Before the fix, the preview-only fallback rejected the `/Name sh` segment and swallowed the visible text until the later stray `EI`.

## Red first

A current-base probe before the source edit returned only:

```text
array (
  0 => 'Before Shading Stray',
  1 => 'Visible After Shading Stray',
)
```

Expected text included `Visible Shading Before Stray`. The inline image payload marker stayed excluded, so the bug was an over-strict post-image content validator rather than payload leakage.

## Implementation

`PdfTextExtractor::contentSegmentGraphicsStateOperatorOperands()` now accepts the PDF shading paint operator `sh` when it has exactly one name operand. That lets the preview-only inline image fallback close at the real terminator before valid shading setup and visible text, while still rejecting arbitrary non-text payload bytes.

## Verification

Focused verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS closes preview-only fallback before shading paint text followed by stray EI operator

1 test files, 342 assertions, 0 failures
```

Adjacent inline-image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 12 selected test files (root lock skipped)
12 test files, 1571 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke emits `preview_only_shading_stray_ei_text_preserved_after_safe_boundary=true`, `visible_text_imported=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted malformed `BI` recovery, tight `ID` boundaries, immediate comments after `ID`, NUL whitespace, tight `EI` sample floors, nested dictionary/text-object decoys, JBIG2/CCITT/unsupported-filter payload closure, slash-delimited `EI`, ActualText/TJ array fallback, post-terminator comments, same-line or q/cm/clipping-path/XObject/color/pattern stray `EI` recovery, stream-filter DecodeParms boundaries, image preview metadata, or OCR/model behavior.

The bounded behavior is specifically `/Name sh` shading paint between a real preview-only inline image terminator and following visible text before a later stray `EI`.

## Dependency closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, preview-only image filter fallback scanner, graphics/text content segment validator, and existing WordPress smoke renderer. Live OCR, Surya/Torch/Texify, PDFium raster parity, and model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF directive.
