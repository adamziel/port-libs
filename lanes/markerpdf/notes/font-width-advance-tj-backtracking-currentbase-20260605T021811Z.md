# markerPDF Font Width TJ Backtracking Extent Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T021811Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T021811Z`

Base accepted HEAD: `8e694cc8945200bae62626398e368e122fd09a0c`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable-PDF text through pdftext/PDFium text extraction before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, the PHP fallback maps the native PDF text-showing and styled-span geometry behavior needed before WordPress import without running pdftext, pypdfium/PDFium, OCR, Python model workers, or external PDF tools.

The bounded parser behavior is `TJ` array backtracking/overprint. PDF numeric adjustments in a `TJ` array move the text cursor without painting glyphs. A positive adjustment can move a later glyph run behind the current cursor, so a styled-span bbox must cover the full drawn glyph extent, not just the final cursor position.

## Implementation

`PdfTextExtractor::nativeTextSpanWidth()` now computes horizontal source-operand width through `textOperandHorizontalExtentWidth()`. For `TJ` arrays, the helper advances through each text element, tracks the minimum and maximum drawn text extents, and applies numeric cursor adjustments without treating the adjustment itself as visible area.

This preserves existing visible text and positioned line grouping while preventing WordPress review bboxes from collapsing when a `TJ` array backtracks.

The focused fixture adds a simple Type1 font with 1000-unit widths and one text-showing array:

```text
[(AB) 3000 (CD)] TJ
```

Before the fix, the native styled bbox was `[0,0,12,12]`, derived from the final text cursor. After the fix, the bbox is `[0,0,36,12]`, covering both the original `AB` extent and the backtracked `CD` extent while visible text remains `ABCD`.

## Evidence

Red-first focused run after adding the fixture, before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
FAIL keeps TJ backtracking adjustments from collapsing native styled font bboxes on current base
Expected: [[0,0,36,12]]
Actual: [[0,0,12,12]]
1 test files, 106 assertions, 1 failures
```

Passing focused run after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
PASS keeps TJ backtracking adjustments from collapsing native styled font bboxes on current base
1 test files, 111 assertions, 0 failures
```

Adjacent CMap/TJ regression guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 79 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `tj_backtrack_text_preserved=true`, `tj_backtrack_span_bbox_preserved=true`, `tj_backtrack_line_bbox_preserved=true`, `tj_backtrack_final_cursor_collapse_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lanes/markerpdf/lane-status.json valid\n";'
```

All passed. `git diff --check -- lanes/markerpdf` passed after this note was added.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1286 -> 1287`
- `wordpressScenarios`: `1248 -> 1249`
- Focused assertion count for `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `100 -> 111`
- Focused PASS case delta: `+1`

## Non-Overlap

This does not repeat accepted simple-font average positive widths, quote-operator styled bbox spacing, unscaled or scaled relative `Td` current-font advance, text-matrix vertical-scale bbox height, negative text-matrix width extent, `Ts` text-rise offsets, unresolved simple-font width slots, vertical Type0 `/W2` styled bboxes, horizontal or vertical source-width `TJ` word-gap insertion, CMap source-width fallback, page graphics-state `cm` transforms, or Form/Image XObject geometry review.

The new boundary is specifically styled-span drawn-extent geometry for a single simple-font `TJ` array whose numeric adjustment backtracks before later glyphs.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content-token parser, text-state operator parser, simple-font width metrics, `TJ` array parser, styled-span bbox construction, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
