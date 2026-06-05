# markerpdf font width advance LastChar boundary current-base

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T035611Z`

Base accepted HEAD: `24c4644c214503440645874cb6dbfb7ef8927022`

## Source truth

PDF simple-font `/Widths` arrays apply to character codes from `/FirstChar` through `/LastChar`. Extra numeric entries after `/LastChar` are outside the declared width range and must not seed explicit glyph metrics, average default-width fallback, styled span geometry, or current-position word-gap decisions.

This slice stays inside the no-GPU markerPDF scope. It does not run OCR, Surya, Texify, Torch, pypdfium rendering, model workers, or external PDF tools.

## Behavior

`PdfTextExtractor::simpleFontExplicitWidths()` now resolves `/LastChar` with the same object-aware numeric helper already used for `/FirstChar`, then ignores any `/Widths` entries whose computed character code is greater than `/LastChar`.

The focused fixture declares `/FirstChar 65 /LastChar 66 /Widths [1000 1000 100]`, then extracts C/D glyphs whose source codes are outside the declared width range. Before the fix, the decoy `100` width for C made a positioned same-line D look like a word gap. After the fix, C falls back to the valid average width, so the narrow positioned movement stays joined while a larger movement still emits a WordPress word gap.

## Red-first evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Before the source fix:

```text
FAIL clips simple-font Widths entries to LastChar before positioned word gaps on current base
Expected: ['CD', 'C D']
Actual:   ['C D', 'C D']
1 test files, 136 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 147 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleEncodingIndirectWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
6 test files, 910 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: the smoke emits `lastchar_width_decoy_gap_excluded=true`, `lastchar_real_positioned_gap_preserved=true`, `lastchar_styled_bboxes_preserved=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

Result: all syntax checks reported no errors; `git diff --check` reported no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status delta

- `phpPass`: `1378 -> 1379`
- `wordpressScenarios`: `1318 -> 1319`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `12 PASS / 135 assertions -> 13 PASS / 147 assertions`

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF dictionary numeric resolver and simple-font width metric path.

## Non-overlap

This patch does not repeat recent font-width work for average positive width fallback, quote spacing, relative/scaled `Td`, text matrix vertical scaling, negative/rotated matrices, `Ts`, `TJ` backtracking, unresolved width slots, text object reset, vertical `/W2`, CMap source-width fallback, Type3 CharProc widths, xref/object-stream parser behavior, OCR/model execution, table recognition, annotations, forms, image filters, metadata, or security preflight.

## Next task

Continue with native no-GPU searchable-PDF font/CMap boundary work, especially object-resolved simple-font width ranges, CID default-width edge cases, or parser-level text-state interactions that affect WordPress paragraph grouping without launching model or raster backends.
