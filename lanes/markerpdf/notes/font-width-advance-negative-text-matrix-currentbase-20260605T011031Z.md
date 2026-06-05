# markerPDF Font Width Negative Text Matrix Advance Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T011031Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T011031Z`

Base accepted HEAD: `5a4ef5c9aefb2ebca93099c5c8d4c04b031c9b2e`

## Source Truth

Upstream `sddai/markerPDF` at the pinned manifest commit routes searchable PDF text through `pdftext.extraction.dictionary_output` before Marker converts page dictionaries into spans, lines, blocks, and Markdown. The no-GPU PHP fallback must therefore preserve native PDF text-state width geometry for styled spans when pdftext, PDFium, OCR, and model execution are intentionally unavailable.

The relevant PDF parser boundary is signed text-matrix advance. A negative text matrix can mirror glyph painting and produce a signed text-position advance, but a WordPress review bbox still needs the absolute font-width extent; otherwise a valid mirrored simple-font span collapses to the native 1pt fallback and downstream link/table/span review geometry is wrong.

## Behavior Added

`PdfTextExtractor::nativeTextSpanWidth()` now uses the absolute positioned font-width extent when a source operand supplies width-aware native geometry. Text line grouping and signed positioned-text decisions remain unchanged; only styled-span bbox width avoids collapsing when the horizontal matrix advance is negative.

The focused fixture adds:

- a simple Type1 font with 1000-unit widths for `A` through `D`;
- `-1 0 0 1 72 720 Tm <4142> Tj` to mirror `AB` through a negative text matrix;
- a following identity-positioned `CD` run to preserve the existing line boundary as `AB CD`;
- styled-span bbox checks proving the mirrored `AB` span is `[0,0,24,12]`, not `[0,0,1,12]`.

## Evidence

Red-first focused run after adding the fixture:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses simple-font average positive width fallback for missing glyph advances on current base
PASS applies quote operator spacing before styled font advance bboxes on current base
PASS uses font-width current text advance before relative Td word-gap decisions on current base
PASS uses scaled text matrix advance before relative Td word-gap decisions on current base
PASS uses text matrix vertical scale before native styled span bboxes on current base
FAIL keeps negative text matrix font widths from collapsing styled bboxes on current base
Expected: [[0,0,24,12],[24,0,48,12]]
Actual: [[0,0,1,12],[1,0,25,12]]
PASS preserves unresolved simple-font width slots before current advance gap decisions
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base
1 test files, 84 assertions, 1 failures
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
PASS preserves unresolved simple-font width slots before current advance gap decisions
PASS uses vertical CIDFont W2 advances for native styled span bboxes on current base
1 test files, 89 assertions, 0 failures
```

Adjacent font/CMap gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php
5 test files, 190 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `negative_text_matrix_line_boundary_preserved=true`, `negative_text_matrix_span_bboxes_preserved=true`, `negative_text_matrix_collapsed_bbox_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, followed by Gutenberg paragraphs including `AB CD`.

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

- `phpPass`: `1226 -> 1227`
- `wordpressScenarios`: `1201 -> 1202`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused assertion count for `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `78 -> 89`

## Non-Overlap

This does not repeat accepted simple-font positive-width averaging, quote-operator styled bbox spacing, unscaled or scaled relative `Td` current-font advance, text-matrix vertical scale bbox height, unresolved simple-font width slots, vertical Type0 `/W2` styled bboxes, Type0 CID resource spacing, Type3 CMap spacing, `TJ` numeric adjustments, or CMap source-width fallback. The new boundary is specifically negative text-matrix horizontal font-width extent for native styled-span bboxes before WordPress review geometry.

## Dependency Closure

No new support component is needed. This patch reuses the native content tokenizer, text-state operator parser, font width metric path, positioned text grouping, styled-span extraction, and WordPress smoke renderer. Full upstream model/OCR parity remains intentionally out of scope under the current no-GPU markerPDF directive.
