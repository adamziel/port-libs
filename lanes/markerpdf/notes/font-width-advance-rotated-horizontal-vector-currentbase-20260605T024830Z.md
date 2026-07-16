# markerPDF font-width advance rotated text-matrix boundary current base

Session: `port-dev-markerpdf-font-width-advance-20260605T024830Z`
Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T024830Z`
Base accepted HEAD: `536a01d545c5e2ebe0d5b0d43addd0dcfd5c75eb`

## Source Truth

Upstream `sddai/markerPDF` at the pinned manifest commit routes searchable PDF text through `pdftext.extraction.dictionary_output` before Marker turns page dictionaries into spans, lines, blocks, and Markdown. The no-GPU PHP fallback therefore needs native span geometry to stay consistent with the font advance data pdftext/PDFium would use for searchable PDFs.

The relevant PDF text-rendering boundary is horizontal text advance under a non-identity text matrix: the horizontal text vector is transformed by the text matrix `(a, b)` components. A 90-degree rotated matrix (`0 1 -1 0`) and a sheared/unit-length vector (`0.6 0.8 0 1`) should preserve a full glyph advance extent for native styled-span bboxes instead of collapsing to the raw `a` coefficient.

## Implementation

`PdfTextExtractor::textSpanLinesFromContentStream()` now tracks a styled-span-only horizontal extent scale derived from `sqrt(a*a + b*b)` when `Tm` is applied. The existing signed `a`-coefficient cursor state remains intact for current line-gap behavior, including the previously accepted negative-text-matrix boundary. `appendNativeTextSpan()` receives the extent scale for bbox width calculation, while text-line cursor advancement and relative `Td` behavior remain unchanged.

The focused fixture adds one rotated simple-font span and one sheared simple-font span. Before the fix the native styled bboxes collapsed to `[[0,0,1,12],[1,0,15.4,12]]`. After the fix they preserve font width advance bboxes as `[[0,0,24,12],[24,0,48,12]]`.

## Verification

Red-first:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result before source change: `1 test files, 117 assertions, 1 failures`; failing case: `uses rotated text matrix horizontal vector for native styled font advance bboxes on current base`.

After patch:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result: `1 test files, 122 assertions, 0 failures`.

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: exits `0`; smoke metadata reports `rotated_text_matrix_horizontal_vector_line_preserved=true`, `rotated_text_matrix_horizontal_vector_bboxes_preserved=true`, `rotated_text_matrix_collapsed_bbox_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent font-width family:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetIndirectWidthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidSetVerticalSurrogateWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontEncodingDifferencesCMapWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcCidSetDescriptorCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcToUnicodeCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsDictionaryGenerationCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFallbackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsFontMatrixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsGenerationBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsNestedDictionaryBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsSubtypeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsTopLevelBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CidSetCMapCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3ColorGlyphResourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
```

Result: `31 test files, 367 assertions, 0 failures`.

Lint and diff checks:

```bash
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status ok\n";'
git diff --check -- lanes/markerpdf
```

Result: all passed.

## Non-Overlap

This does not repeat accepted simple-font average positive width fallback, quote-operator spacing, relative/scaled `Td`, vertical text-matrix scale, negative text matrix cursor behavior, text rise, `TJ` backtracking, unresolved width slots, vertical `/W2` styled-span boxes, Type0 CID width resources, CMap fallback, or page graphics-state `cm` transform slices. The new boundary is specifically styled-span font advance extent for rotated or sheared horizontal text matrices where the text matrix `b` component contributes to the horizontal vector length.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content tokenizer, simple-font width metrics, text-state advance helpers, styled-span extraction path, and WordPress smoke renderer. Full OCR/model/PDFium runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
