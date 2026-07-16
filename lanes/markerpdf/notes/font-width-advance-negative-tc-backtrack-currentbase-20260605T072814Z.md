# markerPDF Font Width Negative Tc Backtrack Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T072814Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T072814Z`

Base accepted HEAD: `6ab7d921878968a04f160c754722667c2cd32bc9`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable-PDF text through pdftext/PDFium extraction before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this PHP lane ports the native PDF text extraction and styled-span geometry behavior needed before WordPress import without running pdftext, pypdfium/PDFium, OCR, Python model workers, or external PDF tools.

The bounded PDF behavior is direct text showing with negative character spacing. `Tc` changes the text cursor between glyphs, but the review bbox should cover the painted glyph interval. A direct `Tj` operand with a negative `Tc` can draw a later glyph behind the final cursor; using only the final cursor width collapses the visible span.

## Implementation

`PdfTextExtractor::textOperandHorizontalExtentWidth()` now computes direct text operands with the same per-element extent helper used by `TJ` arrays. The helper preserves the existing final cursor advance through `advanceTextEndX()` for grouping behavior, then walks glyph starts and ends to track the minimum and maximum painted horizontal extents.

The traversal reuses existing simple-font and CMap source-width helpers, source-key word-spacing detection, and decoded-text fallback spacing. It intentionally keeps the final cursor in the extent range so existing terminal positive `Tc` behavior remains unchanged, while negative spacing cannot collapse the span below the actual drawn glyph coverage.

The focused fixture adds a simple Type1 font with 1000-unit widths and one direct text-showing operator:

```text
BT /FnegTc 12 Tf -30 Tc 1 0 0 1 72 720 Tm <4142> Tj ET
```

Before the fix, native styled extraction returned a bbox width of `6`, derived from the final text cursor. After the fix, the styled span and line bbox are `[0,0,30,12]`, covering both `A` and the backtracked `B`, while visible text remains `AB`.

## Evidence

Red-first focused run after adding the fixture, before source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
FAIL keeps negative character spacing backtracking from collapsing direct Tj styled font bboxes on current base
Expected: [[0,0,30,12]]
Actual: [[0,0,6,12]]
1 test files, 222 assertions, 1 failures
```

Passing focused run after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 227 assertions, 0 failures
```

Adjacent font/CMap regression guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
7 test files, 1067 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `negative_tc_backtrack_text_preserved=true`, `negative_tc_backtrack_span_bbox_preserved=true`, `negative_tc_backtrack_line_bbox_preserved=true`, `negative_tc_final_cursor_collapse_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json","lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'
```

All passed. `git diff --check -- lanes/markerpdf` passed after this note was added.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1573 -> 1574`
- `wordpressScenarios`: `1461 -> 1462`
- `UPSTREAM_TEST_MANIFEST.json` mapped font-width boundary behaviors: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `216 -> 227` assertions
- Focused PASS case delta: `+1`

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, terminal positive `Tc`, relative/scaled `Td`, text-matrix vertical scale, negative/rotated matrix extent, text rise, `TJ` numeric backtracking, unresolved simple-font width slots, `/LastChar` clipping, malformed width ranges, vertical `/W2`, indirect `/W` and `/W2`, Type3 `/FontMatrix`, source-width fallback, xref repair, stream filters, attachments, annotations, forms, tables, or image review.

The new boundary is specifically direct `Tj` styled-span drawn-extent geometry for simple-font glyphs whose negative character spacing moves a later glyph behind the final cursor.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content-token parser, text-state operator parser, simple-font width metrics, ToUnicode/source-boundary helpers, styled-span bbox construction, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
