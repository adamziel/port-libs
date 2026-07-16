# markerPDF Font Width Type3 FontMatrix Widths Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T065555Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T065555Z`

Base accepted HEAD: `b70b567aca418540e07049329182483d4bd89175`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is Type3 font dictionary `/Widths` advance normalization when the Type3 font uses a non-default `/FontMatrix`.

## Source Truth

Pinned markerPDF routes searchable PDF text through pdftext/PDFium-backed extraction before Marker converts page dictionaries into spans, lines, blocks, and Markdown. The native PHP fallback therefore needs font advance geometry to match PDF text-space metrics when pdftext/PDFium and model workers are unavailable.

Type3 font widths are declared in the font glyph coordinate system and are transformed by the font's `/FontMatrix` before text-space advance decisions. The native port already normalized Type3 CharProc `d0`/`d1` width vectors through `/FontMatrix`; this slice applies the same boundary to Type3 dictionary `/Widths` arrays.

## Implementation

`PdfTextExtractor::simpleFontExplicitWidths()` now detects Type3 font dictionaries and normalizes resolved `/Widths` entries through `type3FontMatrixWidthVectorAdvance([$width, 0.0], $fontMatrix)` before those metrics feed:

- current text end positions used by same-line `Tm` word-gap decisions;
- native styled-span bbox width;
- average positive width fallback for missing Type3 glyph slots.

Default Type3 `/FontMatrix [0.001 0 0 0.001 0 0]` keeps existing widths unchanged. Type1/TrueType simple-font widths are unchanged.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Before the source fix:

```text
FAIL normalizes Type3 Widths through FontMatrix before current advance gaps on current base
Expected: ['ABCD', 'AB CD']
Actual:   ['AB CD', 'AB CD']
1 test files, 205 assertions, 1 failures
```

The fixture declares `/Subtype /Type3`, `/FontMatrix [0.002 0 0 0.001 0 0]`, and `/Widths [500 500 500 500]`. Raw `500` widths produced a false positioned gap; normalized `1000` text-space widths preserve the joined first line and the larger second-line gap.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 216 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFont*Width*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProc*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
```

Result:

```text
40 test files, 1322 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: the smoke emits `type3_fontmatrix_widths_false_gap_excluded=true`, `type3_fontmatrix_widths_real_gap_preserved=true`, `type3_fontmatrix_widths_double_gap_output_excluded=true`, `type3_fontmatrix_widths_styled_bboxes_preserved=true`, `type3_fontmatrix_widths_raw_500_bbox_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1543 -> 1544`
- `wordpressScenarios`: `1438 -> 1439`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `18 PASS / 204 assertions -> 19 PASS / 216 assertions`
- Focused PASS case delta: `+1`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, simple-font width parser, Type3 FontMatrix parser, text-state advance helpers, styled-span extraction path, and WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, terminal `Tc`, relative/scaled `Td`, text matrix vertical scaling, negative/rotated matrices, `Ts` text rise, horizontal or vertical `TJ` backtracking, unresolved width slots, text object reset, `/LastChar` clipping, malformed width ranges, direct vertical `/W2` bboxes, indirect `/W`/`W2` parsing, CMap source-width fallback, Type3 CharProc width/FontMatrix handling, xref/object-stream parser behavior, OCR/model execution, table recognition, annotations, forms, image filters, metadata, or security preflight.

The new boundary is specifically Type3 dictionary `/Widths` array entries normalized through `/FontMatrix` before WordPress searchable text grouping and styled-span review.

## Next Task

Continue with native no-GPU searchable-PDF font/CMap boundary work, especially parser-level text-state interactions that affect WordPress paragraph grouping and review geometry without launching model or raster backends.
