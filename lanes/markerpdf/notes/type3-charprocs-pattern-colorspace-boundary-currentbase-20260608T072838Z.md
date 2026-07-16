# Type3 CharProcs Pattern Color-Space Boundary

Slice: `markerpdf-type3-charprocs-boundary-current-base-20260608T072838Z`

Base accepted HEAD: `7a90bcad9d6bff430438ead531970c6cb19a00b6`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to pdftext/PDFium before OCR/layout/model stages. At this native no-GPU parser boundary, Type3 `/CharProcs` are glyph programs: their `d0`/`d1` metrics may drive text advance grouping, but malformed glyph paint setup must not make a late metric authoritative or leak paint payload text into WordPress paragraphs.

PDF color operators use `SCN`/`scn` for pattern and special color spaces. A pattern name operand is valid only when the active stroking/nonstroking color space is Pattern-like; it is not valid under `/DeviceRGB` or the default color space. The PHP fallback therefore has to track pre-metric `CS`/`cs` state before trusting Type3 metrics after pattern-name color operands.

## Red Check

Before the source change:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorSpaceBoundaryCurrentBaseTest.php
```

failed with:

```text
Expected: array (
  0 => 'WideBlock',
  1 => 'Bad Gap',
  2 => 'Raw Gap',
)
Actual: array (
  0 => 'WideBlock',
  1 => 'BadGap',
  2 => 'RawGap',
)

1 test files, 1 assertions, 1 failures
```

That proved the current-base Type3 width parser accepted `/DeviceRGB cs /GlyphPattern scn` and bare `/GlyphPattern scn` before `d0`, then incorrectly used those malformed glyph metrics to remove WordPress word gaps.

## Implementation

`PdfTextExtractor::type3CharProcDeclaredWidthVector()` now tracks active pre-metric stroking and nonstroking color-space names from `CS` and `cs`. `type3CharProcAllowsPreMetricSetupOperator()` passes that state into `SCN`/`scn` validation, so numeric color operands remain valid while pattern-name operands require `/Pattern` or a Pattern-like custom color space.

The focused fixture proves:

- `/Pattern cs /GlyphPattern scn` before `d0` preserves the Type3 CharProc width and joins `WideBlock`;
- `/DeviceRGB cs /GlyphPattern scn` before `d0` is rejected and falls back to `/Widths`, preserving `Bad Gap`;
- bare `/GlyphPattern scn` before `d0` is rejected and falls back to `/Widths`, preserving `Raw Gap`;
- Type3 CharProc payload text and pattern resource names remain excluded from visible WordPress paragraphs.

## Evidence

Focused test after the fix:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorSpaceBoundaryCurrentBaseTest.php
```

Result: `1 test files, 13 assertions, 0 failures`.

Adjacent Type3 color/operator run:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsColorSpaceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPatternResourceFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTextStateBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInitialOperatorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsPathSetupBoundaryCurrentBaseTest.php
```

Result: `6 test files, 58 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-pattern-colorspace-currentbase.php --self-test
```

Result: exits `0` and emits Gutenberg paragraphs for `WideBlock`, `Bad Gap`, and `Raw Gap`, with `valid_pattern_colorspace_width_preserved=true`, `devicergb_pattern_name_metric_rejected=true`, `default_colorspace_pattern_name_metric_rejected=true`, `charproc_payload_visible_text_excluded=true`, `pattern_resource_name_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Changed PHP lint:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontType3CharProcsPatternColorSpaceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-pattern-colorspace-currentbase.php
```

Result: all passed.

## Dependency Closure

No new support component is needed. This slice reuses the native PHP PDF object scanner, exact-generation stream lookup, content tokenizer, Type3 CharProc width parser, FontDescriptor/Widths fallback path, and WordPress smoke harness. No Python, PDFium, pypdfium2, PIL, OCR, Surya, Texify, Torch, Streamlit/FastAPI model worker, GPU/model execution, browser service, or external PDF tool was run.

## Non-Overlap

This does not repeat accepted Type3 CharProc fallback exclusion, direct `d0`/`d1` width parsing, exact-generation CharProc selection, indirect/direct `/CharProcs` dictionary boundaries, FontMatrix/vector advance behavior, pre-metric text paint rejection, non-painting path setup, malformed path operands, graphics-state/marked-content balance, BDC/DP property validation, inline-image paint rejection, pattern color name ordering, private resource fallback exclusion, CMap/CIDSet Type3 width behavior, xref repair, metadata, annotations, forms, image filters, OCR/model behavior, or supplied table/equation handoffs. The bounded behavior is only active color-space validation for Type3 CharProc pre-metric `SCN`/`scn` pattern-name operands.

Root harness: not run - isolated micro-slice.
