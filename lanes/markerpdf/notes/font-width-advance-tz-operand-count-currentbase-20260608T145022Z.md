# markerpdf font-width advance Tz operand-count boundary, 2026-06-08

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T145022Z`
Base: `e204a40179162b2df94e6db36bf203fd0df70d1a`

## Source truth

- `lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` records upstream `sddai/markerPDF` as a pdftext/pypdfium2-backed searchable-PDF extraction pipeline. This slice stays in the native no-GPU text parser path and does not invoke OCR, Surya, Texify, Torch, pypdfium, Python, or external PDF tools.
- PDFium maps the `Tz` operator to `Handle_SetHorzScale`; that handler returns unless the parameter count is exactly one, then applies the horizontal scale. Reference: <https://pdfium.googlesource.com/pdfium/+/refs/heads/main/core/fpdfapi/page/cpdf_streamcontentparser.cpp>
- The existing local overlarge-finite `Tz` boundary rejects numeric overflow. This slice is distinct: malformed extra operands such as `100 50 Tz` must preserve the previous valid horizontal text scale rather than accepting the trailing operand.

## Behavior

Before this patch, `PdfTextExtractor::textHorizontalScaleOperand()` accepted any non-empty operand list and used the last token. A malformed content stream could therefore change horizontal scale from a valid `100 Tz` to `50` through `100 50 Tz`, shrinking glyph advances and creating a false positioned word gap.

The implementation now accepts `Tz` only when exactly one operand is present. Malformed extra-operand `Tz` returns `null`, so the active text state remains unchanged across text runs, line grouping, and styled-span bbox generation.

## Evidence

Red-first focused run before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTzOperandCountBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed extra-operand Tz before font-width advance grouping on current base
Expected: ['ABCD', 'EFGH']
Actual: ['ABCD', 'EF GH']
1 test files, 1 assertions, 1 failures
```

Focused run after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTzOperandCountBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed extra-operand Tz before font-width advance grouping on current base
1 test files, 15 assertions, 0 failures
```

Focused font-width family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceTzOperandCountBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvance*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidth*CurrentBaseTest.php
Focused test run: 16 selected test files (root lock skipped)
16 test files, 960 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-tz-operand-count-currentbase.php
exits 0 with malformed_extra_operand_tz_rejected=true, preserved_previous_horizontal_scale=true, false_word_gap_excluded=true, styled_bboxes_preserved=true, executes_python_or_models=false, executes_external_pdf_tools=false
```

## Non-overlap

This avoids accepted/ready font-width slices for average MissingWidth fallback, quote-operator spacing, relative/absolute `Td` and `Tm` gaps, vertical `/W2`, Type3 FontMatrix/CharProc widths, malformed `Tf`, overlarge finite `Tz`, and quote-operator tailed operands. It only changes the `Tz` operand-count boundary.

## Dependency closure

No new support component is needed. The existing native PHP content-stream parser, font-width metric resolver, and styled-span extractor are reused. GPU/model/OCR/pdftext/pypdfium execution remains intentionally out of scope for this markerPDF lane.

## Next task

Continue with non-overlapping native searchable-PDF parser behavior, especially CMap/font-width boundaries, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
