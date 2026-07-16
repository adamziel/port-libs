# markerpdf font width advance malformed range current-base

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T053811Z`

Base accepted HEAD: `c89187f34342898caf9881c6ca3bd7bed3e29bfc`

## Source truth

Upstream `sddai/markerPDF` at the pinned manifest commit routes searchable PDF text through pdftext dictionary output before Marker converts spans, lines, blocks, and Markdown. Under the current no-GPU scope, the native PHP fallback must keep text grouping and styled-span geometry aligned with PDF font advance data when pdftext, pypdfium/PDFium, OCR, and model workers are unavailable.

The relevant PDF parser boundary is simple-font width range validation. `/Widths` array entries are positional metrics for byte character codes beginning at integer `/FirstChar` and, when present, ending at integer `/LastChar`. Non-integral or out-of-byte range operands are malformed and must not seed explicit glyph metrics, average default-width fallback, styled span geometry, or current-position word-gap decisions.

## Behavior

`PdfTextExtractor::simpleFontExplicitWidths()` now validates simple-font `/FirstChar` and `/LastChar` values as finite integral byte codes from 0 through 255 before applying `/Widths`. A malformed present `/LastChar` or a reversed range now rejects that explicit width range instead of truncating decimals with `(int)` and applying decoy widths to source glyphs.

The focused fixture declares `/FirstChar 67.75 /LastChar 68.25 /Widths [100 100 1000 1000]` for visible C/D/E/F glyphs. Before the fix, the decimal casts mapped narrow widths onto C and D, producing a false first-line WordPress gap and collapsed styled bboxes. After the fix, the malformed range is ignored, safe source-boundary advances preserve `CDEF` on the narrow line, and the larger positioned movement still emits `CD EF`.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Before the source fix:

```text
FAIL rejects malformed simple-font width range operands before current advance gaps on current base
Expected: ['CDEF', 'CD EF']
Actual:   ['CD EF', 'CD EF']
1 test files, 182 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 193 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
6 test files, 1001 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: the smoke emits `malformed_range_decimal_widths_ignored=true`, `malformed_range_real_positioned_gap_preserved=true`, `malformed_range_double_gap_output_excluded=true`, `malformed_range_styled_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status delta

- `phpPass`: `1477 -> 1478`
- `wordpressScenarios`: `1389 -> 1390`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `16 PASS / 181 assertions -> 17 PASS / 193 assertions`

## Dependency closure

No new support component is needed. This slice reuses the native PDF object scanner, simple-font width metric parser, source-boundary advance fallback, styled-span extraction path, and WordPress smoke renderer. Full OCR/model/PDFium runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.

## Non-overlap

This patch does not repeat accepted simple-font average positive width fallback, quote spacing, terminal character spacing, relative/scaled `Td`, text matrix vertical scaling, negative/rotated matrices, `Ts`, `TJ` backtracking, unresolved width slots, `/LastChar` clipping, text object reset, vertical `/W2`, CMap source-width fallback, indirect simple-font `/FirstChar`/`/Widths`, indirect CIDFont `/W` or `/W2`, Type3 CharProc widths, xref/object-stream parser behavior, OCR/model execution, table recognition, annotations, forms, image filters, metadata, or security preflight. The new boundary is specifically malformed non-integral simple-font width range operands before native WordPress word-gap and styled-span geometry decisions.

## Next task

Continue native no-GPU searchable-PDF font/CMap boundary work with non-overlapping font metric validation, CID default-width edge cases, or parser-level text-state interactions that affect WordPress paragraph grouping without launching model or raster backends.
