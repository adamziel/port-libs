# markerPDF Font Width Text Rise Boundary Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T014552Z`
Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T014552Z`
Base accepted HEAD: `b828ac3b472ad91b3570084ccb5b89f5b3613216`

## Source Truth

Upstream `sddai/markerPDF` at the pinned manifest commit routes searchable-PDF text through the pdftext dictionary-output boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the no-GPU directive, the native PHP fallback must preserve PDF text-state geometry for WordPress review spans without running pdftext, PDFium, pypdfium, Python model workers, OCR, or external PDF tools.

The bounded parser behavior is PDF `Ts` text rise. Text rise moves painted glyphs relative to the baseline without changing the horizontal font-width advance or visible text. Native styled-span bboxes therefore need the rise offset so superscript/subscript-style review geometry is not flattened to the zero baseline.

## Implementation

`PdfTextExtractor::textSpanLinesFromContentStream()` now tracks the `Ts` text-state operand, saves/restores it with the existing q/Q text-state stack, and passes it into native span bbox construction. `nativeTextSpanBbox()` offsets bbox y coordinates by the finite text-rise value while preserving existing width calculations from `/Widths`, `/W`, `/DW`, `Tc`, `Tw`, `Tz`, `TJ`, quote spacing, text-matrix scale, negative matrix extent, unresolved width slots, and vertical `/W2` paths.

The focused fixture uses one Type1 font with 1000-unit widths and four adjacent text-showing operators:

- baseline `AB`;
- `6 Ts` raised `CD`;
- `-3 Ts` lowered `EF`;
- `0 Ts` reset `GH`.

Visible WordPress paragraph text remains `ABCDEFGH`; only native styled-span bboxes and the line bbox move to the raised/lowered review geometry.

## Evidence

Red-first focused run after adding the fixture, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
FAIL applies text rise before native styled font advance bboxes on current base
Expected: [[0,0,24,12],[24,6,48,18],[48,-3,72,9],[72,0,96,12]]
Actual: [[0,0,24,12],[24,0,48,12],[48,0,72,12],[72,0,96,12]]
1 test files, 95 assertions, 1 failures
```

Passing focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
PASS uses font-width current text advance before relative Td word-gap decisions on current base
PASS uses scaled text matrix advance before relative Td word-gap decisions on current base
PASS uses text matrix vertical scale before native styled span bboxes on current base
PASS keeps negative text matrix font widths from collapsing styled bboxes on current base
PASS applies text rise before native styled font advance bboxes on current base
PASS preserves unresolved simple-font width slots before current advance gap decisions
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base
1 test files, 100 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `text_rise_preserves_horizontal_advance=true`, `text_rise_span_bboxes_preserved=true`, `text_rise_line_bbox_preserved=true`, `text_rise_zero_baseline_fallback_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by the `ABCDEFGH` Gutenberg paragraph.

Adjacent font/text regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 829 assertions, 0 failures
```

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'
git diff --check -- lanes/markerpdf
```

All passed.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1257 -> 1258`
- `wordpressScenarios`: `1225 -> 1226`
- Focused assertions for `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `89 -> 100`
- Mapped upstream denominator unchanged; this is an additive current-base behavior inside the already mapped native PDF text/font geometry boundary.

## Non-Overlap

This does not repeat accepted simple-font average positive widths, quote-operator spacing, relative `Td` current-font advance, scaled text-matrix `Td`, text-matrix vertical scale, negative text-matrix width extent, unresolved width-array slot preservation, vertical CIDFont `/W2` styled bboxes, CMap source-width fallback, Type3 CharProc width handling, page graphics-state `cm` transforms, or Form/Image XObject geometry review. The new boundary is specifically `Ts` text-rise offsets for native styled-span bboxes before WordPress review geometry.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content-token parser, text-state operator parser, font width metric path, styled-span bbox construction, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
