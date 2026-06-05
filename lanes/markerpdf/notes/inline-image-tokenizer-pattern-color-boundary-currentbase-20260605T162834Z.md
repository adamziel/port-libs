# markerPDF inline image tokenizer pattern color boundary

Micro-slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260605T162834Z`

Base accepted HEAD: `c0e71447bb6ce34af94a2d4d96a552f5aa1446a1`

## Source truth

Upstream `sddai/markerPDF` at manifest-pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF page text through parser-backed extraction before image/OCR/model stages. In this no-GPU native scope, inline `BI ... ID ... EI` bytes are image payload and must not leak into WordPress paragraphs, while valid content-stream graphics state and following text remain visible.

This slice covers a fresh tokenizer fallback boundary: a preview-only inline image followed by valid `/Pattern cs`, numeric tint plus `/P1 scn`, visible text, and then a stray `EI` operator. The fallback scanner previously rejected the pattern-name `scn` operand and swallowed the visible text until the later stray `EI`.

## Red first

A throwaway current-base probe before the source edit returned only:

```text
['Before Pattern Color Stray', 'Visible After Pattern Color Stray']
```

Expected text included `Visible Pattern Color Before Stray`. The payload marker `Pattern Color Payload Noise` remained excluded, proving the bug was not payload leakage but an over-strict post-image content scanner.

## Implementation

`PdfTextExtractor::contentSegmentGraphicsStateOperatorOperands()` now accepts `SCN`/`scn` color operators whose final operand is a pattern name, while still requiring every preceding operand to be numeric. This keeps the preview-only inline image fallback closed over raster bytes but allows valid pattern-color graphics-state setup before visible WordPress text.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS closes preview-only fallback before pattern color-state text followed by stray EI operator
1 test files, 321 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 11 selected test files (root lock skipped)
11 test files, 1489 assertions, 0 failures
```

Attempted broader adjacent run including `PdfTextExtractorTest.php` showed all inline-image cases passing but four unrelated current-base CMap/ToUnicode failures in `PdfTextExtractorTest.php`.

Syntax and smoke:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php
```

The smoke exits 0 and emits `preview_only_pattern_color_state_stray_ei_text_preserved_after_safe_boundary=true`, `real_inline_image_payload_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-overlap

This does not repeat accepted malformed `BI` preamble recovery, tight `ID`/`EI` sample floors, comment/NUL/compact dictionary boundaries, preview-only JBIG2/CCITT/DCT/JPX framing, unsupported-filter closure, visible literal/TJ array fallback, marked `/ActualText` or `/Alt` replacement fallback, path/clip/XObject/ordinary numeric color graphics-state wrappers, or stream-filter DecodeParms boundaries.

The bounded new behavior is only valid pattern-color `SCN`/`scn` operands between a preview-only inline-image fallback and visible text before a later stray `EI`.

## Dependency closure

No new support component is needed. This reuses the native PHP content tokenizer, inline-image dictionary parser, preview-only image filter fallback scanner, graphics-state operand validator, and existing WordPress smoke renderer. Live OCR, Surya/Texify/Torch models, PDFium/pypdfium rendering, PIL, Streamlit/FastAPI workers, and external PDF tools remain intentionally out of scope under the markerPDF no-GPU directive.
