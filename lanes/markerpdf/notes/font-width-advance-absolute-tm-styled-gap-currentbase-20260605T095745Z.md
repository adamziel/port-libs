# markerPDF Font Width Advance Absolute Tm Styled Gap Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T095745Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T095745Z`

Base accepted HEAD: `f277d8c62948bf03fc46bb2f8adb59a7a1bac47e`

## Source Truth

Pinned upstream `sddai/markerPDF` delegates searchable-PDF text extraction to `pdftext.extraction.dictionary_output(..., keep_chars=False, ...)` and then carries each span `text`, `bbox`, font name, flags, weight, and size into Marker `Span` objects before WordPress-oriented conversion. See https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py.

PDF positioned text matrices are coordinate transformations. For same-line absolute `Tm`, native styled review geometry must preserve real positioned gaps when the previous text run has an ordinary positive horizontal matrix, while existing mirrored/rotated text-matrix normalization remains separate. The pypdf transformation helper documents the same six-element matrix shape and point application boundary: https://raw.githubusercontent.com/py-pdf/pypdf/5.4.0/pypdf/_page.py.

This slice stays inside the no-GPU markerPDF scope. It does not run OCR, Surya, Texify, Torch, PDFium/pypdfium rendering, model workers, Python helpers, or external PDF tools.

## Behavior

`PdfTextExtractor::textSpanLinesFromContentStream()` now preserves absolute same-line `Tm` word-gap geometry in native styled-span bboxes for simple-font width advances. The new `styledTextMatrixGapWidth()` helper compares the next `Tm` x-coordinate against the prior drawn text extent and records that gap before the next styled span is appended.

The helper is intentionally limited to simple-font maps with ordinary positive horizontal text matrices. Existing current-base tests keep CMap/CID source-width fallback, mirrored matrices, and rotated text-matrix styled bboxes normalized rather than injecting misleading horizontal gaps.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Before the source fix:

```text
FAIL preserves absolute Tm word-gap geometry in native styled bboxes on current base
Expected second line bboxes: [[0,0,24,12],[36,0,60,12]]
Actual second line bboxes:   [[0,0,24,12],[24,0,48,12]]
1 test files, 276 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 282 assertions, 0 failures
```

The new case adds one PASS case and 15 focused assertions. It proves the first absolute `Tm` line remains `ABCD` with continuous bboxes, while the second line remains visible as `AB CD` and preserves the styled bbox gap `[36,0,60,12]` instead of collapsing to `[24,0,48,12]`.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

Result: emits `absolute_tm_styled_gap_second_bboxes_preserved=true`, `absolute_tm_styled_gap_compaction_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Adjacent font/CMap regression check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php
```

Result:

```text
5 test files, 254 assertions, 0 failures
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1695 -> 1696`
- `wordpressScenarios`: `1554 -> 1555`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `23 PASS / 267 assertions -> 24 PASS / 282 assertions`

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, terminal `Tc`, relative/scaled `Td`, text-matrix vertical scale, negative/rotated matrix extent normalization, text rise, `TJ` backtracking, `TJ` drawn-extent same-line `Tm` plain gap classification, unresolved width slots, `/LastChar` clipping, malformed width ranges, vertical `/W2`, indirect `/W` and `/W2`, Type3 `/FontMatrix`, CMap source-width fallback, xref repair, stream filters, attachments, annotations, forms, tables, image review, or model/OCR work.

The new boundary is specifically styled-span review geometry for an absolute same-line `Tm` word gap after ordinary positive horizontal simple-font width text advance.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, content-token parser, simple-font width metrics, text-position advance helpers, styled-span bbox path, and WordPress smoke renderer. Full upstream pdftext/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
