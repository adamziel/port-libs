# markerPDF Font Width Vertical TJ Backtracking Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T061332Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T061332Z`

Base accepted HEAD: `f35a619c7f21a255877365c107bd8809c41d57e8`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is vertical-writing `TJ` styled-span geometry. Horizontal `TJ` arrays already tracked min/max drawn X extent for backtracking adjustments; vertical Type0 fonts with `/W2` metrics now get the same protection along the Y axis so WordPress review bboxes do not collapse to the final cursor.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through pdftext/PDFium-backed extraction before Marker converts page dictionaries into spans, lines, blocks, and Markdown. The native PHP fallback therefore needs styled-span geometry to stay consistent with font-width advance data when pdftext/PDFium and model workers are unavailable.

PDF `TJ` numeric adjustments move the text cursor without painting glyphs. For vertical writing mode, a negative adjustment can move the next glyph run back across the previous drawn segment. The review bbox must cover both drawn clusters, not just the final Y cursor after the adjustment and later text.

## Implementation

`PdfTextExtractor::nativeTextSpanBbox()` now routes vertical source operands through `textOperandVerticalExtentHeight()`. The helper mirrors the horizontal extent helper: it walks `TJ` array text elements, advances with `/W2` and `/DW2` glyph displacements, applies numeric cursor adjustments, and records the minimum and maximum drawn Y coordinates.

Direct vertical `Tj` operands keep the same result because their extent height is still the absolute vertical advance. Horizontal text and existing line-grouping behavior are unchanged.

## Red-First Evidence

A throwaway fixture with `[<00010002> -3000 <00030004>] TJ` under a vertical Type0 font decoded visible text as `Ve rt` but produced a collapsed styled bbox:

```text
array (
  0 =>
  array (
    0 => 0.0,
    1 => 0.0,
    2 => 12.0,
    3 => 12.0,
  ),
)
```

After the fix, the focused test asserts `[0,0,12,36]`, covering both drawn vertical clusters.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 204 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
```

Result:

```text
5 test files, 1006 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `vertical_tj_backtrack_text_gap_preserved=true`, `vertical_tj_backtrack_bbox_preserved=true`, `vertical_tj_final_cursor_collapse_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'
git diff --check -- lanes/markerpdf
```

Result: PHP lint reported no syntax errors for changed PHP files, both JSON files validated, and the diff check reported no whitespace errors.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1506 -> 1507`
- `wordpressScenarios`: `1410 -> 1411`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `17 PASS / 193 assertions -> 18 PASS / 204 assertions`
- Focused PASS case delta: `+1`

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content-token parser, Type0 CMap parser, CIDFont `/W2` metric parser, `TJ` array parser, text-state advance helpers, styled-span extraction path, and WordPress smoke renderer.

Full upstream runner/model parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext, pypdfium2/PDFium, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark/model downloads, and external OCR/rendering helpers.

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, terminal `Tc`, relative/scaled `Td`, text matrix vertical scaling, negative/rotated matrices, `Ts` text rise, horizontal `TJ` backtracking, unresolved width slots, text object reset, `/LastChar` clipping, malformed width ranges, direct vertical `/W2` bboxes, indirect `/W`/`W2` parsing, CMap source-width fallback, Type3 CharProc widths, xref/object-stream parser behavior, OCR/model execution, table recognition, annotations, forms, image filters, metadata, or security preflight.

The new boundary is specifically vertical-writing Type0 `TJ` backtracking bbox extent under `/W2` metrics before WordPress styled-span review.

## Next Task

Continue with native no-GPU searchable-PDF font/CMap boundary work, especially parser-level text-state interactions that affect WordPress paragraph grouping and review geometry without launching model or raster backends.
