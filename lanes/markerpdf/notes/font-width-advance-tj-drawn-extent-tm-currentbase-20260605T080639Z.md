# markerPDF Font Width TJ Drawn Extent Tm Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T080639Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T080639Z`

Base accepted HEAD: `90c134ef160d0dae68131072cad507459f78c7e8`

## Source Truth

Pinned upstream `sddai/markerPDF` routes searchable-PDF text through pdftext/PDFium extraction before Marker converts page dictionaries into spans, lines, blocks, and Markdown. Under the current no-GPU directive, this PHP lane ports the native searchable-PDF extraction behavior needed before WordPress import without running pdftext, pypdfium/PDFium, OCR, Python model workers, or external PDF tools.

The bounded PDF behavior is a `TJ` array followed by another same-line `Tm`. PDF `TJ` numeric adjustments move the text cursor, but they do not erase already painted glyph coverage. When a positive `TJ` adjustment backtracks the final cursor, the next same-line `Tm` gap decision must compare against the rightmost painted glyph extent, not only the backtracked final cursor.

## Implementation

`PdfTextExtractor::textLinesFromContentStream()` now keeps two horizontal notions after text showing:

- final cursor advance, via `advanceTextEndXForOperand(..., includeTerminal: true)`;
- rightmost painted horizontal extent, via the new `textOperandHorizontalDrawnEndX()` helper.

The helper walks direct operands and `TJ` array elements through the existing source-width-aware `textElementHorizontalExtent()` and numeric `adjustTextEndX()` logic. It returns the maximum painted X for text elements while still allowing the final cursor to backtrack for the next real text advance.

The focused fixture uses a simple Type1 font with 1000-unit widths:

```text
BT /FtjExtent 12 Tf
1 0 0 1 72 720 Tm [(AB) 3000 (CD)] TJ 1 0 0 1 100 720 Tm (EF) Tj
T* 1 0 0 1 72 704 Tm [(AB) 3000 (CD)] TJ 1 0 0 1 116 704 Tm (EF) Tj
ET
```

Before the fix, the near `Tm` at `100` was compared against the backtracked final cursor and produced `ABCD EF`. After the fix it produces `ABCDEF`; the farther `Tm` at `116` still produces `ABCD EF`.

## Evidence

Red-first in-memory probe before source repair:

```text
extractTextLines => ['ABCD EF', 'ABCD EF']
expected         => ['ABCDEF', 'ABCD EF']
```

Passing focused run after source repair:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 239 assertions, 0 failures
```

Adjacent font/CMap regression guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
7 test files, 1089 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `tj_drawn_extent_near_tm_gap_excluded=true`, `tj_drawn_extent_real_tm_gap_preserved=true`, `tj_drawn_extent_double_gap_output_excluded=true`, `tj_drawn_extent_styled_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

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

- `phpPass`: `1604 -> 1605`
- `wordpressScenarios`: `1485 -> 1486`
- `UPSTREAM_TEST_MANIFEST.json` mapped font-width boundary behaviors: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `227 -> 239` assertions
- Focused PASS case delta: `+1`

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, terminal positive `Tc`, relative/scaled `Td`, text-matrix vertical scale, negative/rotated matrix extent, text rise, `TJ` styled bbox backtracking, direct `Tj` negative character-spacing bbox backtracking, unresolved simple-font width slots, `/LastChar` clipping, malformed width ranges, vertical `/W2`, indirect `/W` and `/W2`, Type3 `/FontMatrix`, source-width fallback, xref repair, stream filters, attachments, annotations, forms, tables, or image review.

The new boundary is specifically same-line `Tm` word-gap classification after a horizontal `TJ` array whose numeric adjustment backtracks the final text cursor behind the rightmost painted glyph extent.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content-token parser, text-state operator parser, simple-font width metrics, CMap/source-width helpers, `TJ` array parser, styled-span bbox construction, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
