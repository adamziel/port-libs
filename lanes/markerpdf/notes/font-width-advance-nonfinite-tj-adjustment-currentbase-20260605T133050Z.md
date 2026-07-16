# markerPDF Font Width Non-Finite TJ Adjustment Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T133050Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T133050Z`

Base accepted HEAD: `f142d7b9b18cd05cbd5f51482c8462a8ab4294f0`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable PDF text through pdftext/PDFium before Marker groups text into lines, spans, blocks, and Markdown. Under the current no-GPU markerPDF scope, this PHP lane maps the native PDF text-showing boundary needed before WordPress import without running OCR, Surya, Texify, Torch, PDFium, Python model workers, or external PDF tools.

The bounded PDF behavior is a malformed numeric operand inside a `TJ` text-showing array. PDF `TJ` numeric adjustments affect the text cursor, but a token that overflows to a non-finite PHP float must be rejected before it can create phantom word gaps or infinite styled-span geometry.

## Behavior Added

`PdfTextExtractor::numericOperand()` now returns `null` for non-finite parsed floats. This keeps overlarge numeric operands out of text-positioning operators, including `TJ`, while preserving existing finite numbers, decimal operands, font-width metrics, and CMap/source-width grouping behavior.

The focused fixture uses a simple Type1 font with 1000-unit widths and a `TJ` array containing a 400-digit adjustment between `AB` and `CD`, followed by an absolute `Tm` at the expected current advance for `EF`. The native extractor now emits one WordPress line `ABCDEF`, keeps runs as `ABCD` and `EF`, and preserves finite styled bboxes `[[0,0,48,12],[48,0,72,12]]`.

## Evidence

Red-first focused run after adding the fixture, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 355 assertions, 1 failures
```

The failing new case expected `['ABCDEF']`, but the non-finite `TJ` adjustment produced `['ABCD EF']`.

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 366 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-nonfinite-tj-adjustment-currentbase.php
```

The smoke emits `nonfinite_tj_adjustment_ignored=true`, `phantom_word_gap_excluded=true`, `styled_bboxes_are_finite=true`, `styled_bboxes_preserved=true`, `line_bbox_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by a Gutenberg paragraph containing `ABCDEF`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1868 -> 1869`
- `wordpressScenarios`: `1695 -> 1696`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `354 -> 366` assertions
- Focused PASS case delta: `+1`

## Non-Overlap

This does not repeat accepted simple-font positive-width averaging, quote-operator spacing, terminal character spacing, relative or scaled `Td`, absolute `Tm` styled gaps, text matrix vertical scale, negative or rotated text matrices, text rise, horizontal/vertical `TJ` backtracking, `TJ` drawn extent before same-line `Tm`, unresolved width slots, exact-generation `/Widths`, `/LastChar` clipping, malformed width range rejection, non-finite `/Widths` entries, Type0 `/W` or `/W2` arrays, negative first CID rejection, or Type3 FontMatrix width normalization. The new boundary is only non-finite numeric `TJ` adjustment rejection before text advance, word-gap, and styled-bbox calculation.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, content-token parser, numeric operand parser, `TJ` array parser, simple-font width metrics, text-line grouping, styled-span bbox construction, and WordPress smoke renderer. Full upstream OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
