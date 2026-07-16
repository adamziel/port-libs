# markerPDF Type3 FontMatrix Vector Advance Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T092023Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T092023Z`

Base accepted HEAD: `fc4142f9a1ebfb0250d0ec3f3e8136d576d9fb0f`

## Source Truth

Upstream `sddai/markerPDF` delegates searchable PDF text extraction to
pdftext/PDFium before Marker groups text into lines, spans, blocks, and
Markdown. Under the no-GPU markerPDF scope, the native PHP parser must preserve
the same PDF text-advance boundary for searchable Type3 fonts without running
OCR, Surya, Texify, Torch, PDFium, or external PDF tools.

The bounded parser behavior here is scalar Type3 `/Widths` entries under a
skewed `/FontMatrix`. A scalar width becomes a transformed vector. The previous
native fallback used only the transformed X component, so a non-axis-aligned
FontMatrix could underestimate the current glyph extent, split a no-gap
WordPress line, and emit x-only styled-span bboxes.

## Behavior Added

`PdfTextExtractor` now has a separate scalar Type3 `/Widths` advance path that
uses the full transformed FontMatrix vector extent. The existing Type3
CharProc `d0`/`d1` `wx wy` path remains on the accepted projection behavior, so
the prior CharProc width-vector boundary is preserved.

The focused fixture adds a Type3 font with:

- `/FontMatrix [0.0006 0.0008 0 0.001 0 0]`;
- `/Widths [1000 1000 1000 1000]`;
- a no-gap `AB` then `CD` line whose current positions require 24pt extent;
- a larger-gap second line that still renders as `AB CD`;
- styled bbox assertions proving the first-line spans are `[0,0,24,12]` and
  `[24,0,48,12]`, not the old x-only `[0,0,14.4,12]` shape.

## Evidence

Red-first focused run after adding the fixture, before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 255 assertions, 1 failures
```

The failing new case expected `['ABCD', 'AB CD']`, but the x-only FontMatrix
projection produced `['AB CD', 'AB CD']`.

Focused run after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 267 assertions, 0 failures
```

Adjacent Type3/font extraction sweep:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthPrecedenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsWidthVectorBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
6 test files, 927 assertions, 0 failures
```

During verification, this adjacent sweep initially caught a regression in the
accepted Type3 CharProc `wx wy` vector fixture. The final patch split scalar
`/Widths` extent from CharProc width-vector projection, and the same adjacent
gate passed.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `type3_fontmatrix_vector_false_gap_excluded=true`,
`type3_fontmatrix_vector_real_gap_preserved=true`,
`type3_fontmatrix_vector_styled_bboxes_preserved=true`,
`type3_fontmatrix_vector_x_only_bbox_excluded=true`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`, followed by Gutenberg paragraphs including
the new `ABCD` and `AB CD` fixture lines.

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

- `phpPass`: `1667 -> 1668`
- `wordpressScenarios`: `1532 -> 1533`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused file: `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php` adds one
  TestRunner case and now passes with `267` assertions.

## Non-Overlap

This does not repeat accepted simple-font positive-width averaging,
quote-operator styled bbox spacing, relative `Td` font advance, text-matrix
vertical scale, negative text-matrix bbox extent, text rise, horizontal or
vertical `TJ` backtracking, unresolved width slots, `LastChar` clipping,
malformed width range rejection, rotated text matrices, Type0 vertical `/W2`,
indirect CID `/W` and `/W2` arrays, scalar Type3 FontMatrix normalization, or
accepted Type3 CharProc `wx wy` vector projection. The new boundary is only
scalar Type3 `/Widths` entries transformed through skewed FontMatrix vector
extent before native WordPress grouping and styled-span review geometry.

## Dependency Closure

No new support component is needed. This reuses the native PDF tokenizer,
FontMatrix parser, simple-font width-array parser, Type3 subtype detection,
positioned text grouping, styled-span extraction, and WordPress smoke renderer.
Full upstream OCR/model benchmark parity remains intentionally out of scope
under the current no-GPU markerPDF directive.
