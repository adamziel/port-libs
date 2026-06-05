# markerPDF Font Width Text Object Reset Current Base

Session: `port-dev-markerpdf-font-width-advance-20260605T032051Z`

Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260605T032051Z`

Base accepted HEAD: `82ff7b09c5e0123addfe77de2dff76eaf17d3465`

## Scope

This patch stays inside the native no-GPU markerPDF searchable-PDF parser boundary. It does not run OCR, Surya, Texify, Torch, PDFium, pypdfium, model workers, Python helpers, or external PDF tools.

The bounded behavior is a font-width advance text-object boundary in structured styled output. A prior `Tm` with horizontal scale `0.5` inside one `BT`/`ET` object must not leak into `TJ` word-gap decoding and styled bbox extent for a later `BT` text object.

## Source Truth

Pinned upstream markerPDF routes searchable PDF text through the pdftext dictionary-output boundary before Marker converts page dictionaries into spans, lines, blocks, and Markdown:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://github.com/datalab-to/pdftext

The native PHP fallback therefore has to preserve text-object-local font advance state in span text and bbox geometry before WordPress import when pdftext/PDFium and model execution are intentionally unavailable.

## Native Behavior Added

`PdfTextExtractor::textSpanLinesFromContentStream()` now resets `currentTextMatrixHorizontalScale` when it sees `BT` or `ET`, matching the existing styled-span reset for horizontal extent and vertical scale. This prevents a scaled text matrix from a previous text object from halving later `TJ` numeric adjustments and styled bbox width.

The focused PDF fixture uses:

- `BT /Freset 12 Tf 0.5 0 0 1 72 720 Tm <4142> Tj ET`
- a new text object: `BT /Freset 12 Tf 72 704 Td [(CD) -1000 (EF)] TJ ET`

Before the fix, the plain text path emitted `CD EF`, but styled spans decoded the second line as `CDEF` and produced a stale half-scale bbox `[[0,0,30,12]]`. After the fix, styled spans preserve `CD EF` and `[[0,0,60,12]]`.

## Evidence

Red-first focused run before restoring the two-line reset:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
FAIL resets text matrix horizontal scale between text objects before styled TJ word gaps on current base
Expected: ['AB', 'CD EF']
Actual: ['AB', 'CDEF']
1 test files, 127 assertions, 1 failures
```

Focused passing run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
1 test files, 135 assertions, 0 failures
```

Adjacent font/CMap gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php
5 test files, 264 assertions, 0 failures
```

Broader text extractor:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
1 test files, 628 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
```

The smoke emits `text_object_reset_tj_line_gap_preserved=true`, `text_object_reset_styled_span_gap_preserved=true`, `text_object_reset_styled_bbox_preserved=true`, `text_object_reset_stale_half_scale_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Static checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-font-width-advance-boundary-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " valid\n"; }'
lanes/markerpdf/lane-status.json valid
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json valid

git diff --check -- lanes/markerpdf
passed
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `1346 -> 1347`
- `wordpressScenarios`: `1292 -> 1293`
- `mappedPdfFontWidthAdvanceBoundaryCurrentBaseBehaviors`: `3 -> 4`
- Focused assertion count for `PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`: `122 -> 135`
- Focused PASS case delta: `+1`

## Non-Overlap

This does not repeat accepted simple-font average widths, quote-operator spacing, relative/scaled `Td`, text-matrix vertical bbox height, negative text matrix extent, `Ts` text rise, `TJ` backtracking extent, unresolved width slots, rotated/sheared text-matrix bbox extent, vertical Type0 `/W2` bboxes, CMap source-width fallback, Form XObject resources, page resource inheritance, or image/filter boundaries.

The new boundary is specifically `BT`/`ET` text-object reset of the styled-span horizontal text-matrix scale before later `TJ` word-gap decoding and bbox geometry.

## Dependency Closure

No new support component is needed. This patch reuses the native content tokenizer, text-state operator parser, simple-font width metrics, `TJ` array parser, styled-span extraction path, and WordPress smoke renderer. Full upstream model/OCR/PDFium runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
