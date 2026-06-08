# markerpdf font width advance overflow CID range current-base

Slice: `markerpdf-font-width-advance-boundary-current-base-20260608T231427Z`

Accepted base: `2e9d106a5085fd98176497cfade7ca0a16be2709`

## Behavior

This patch keeps native PDF font-advance grouping fail-closed for malformed
CIDFont width ranges. `/W` and `/W2` range rows whose upper CID exceeds
`0xffff` are now rejected instead of being clamped into valid CIDs. That keeps
overflowing font metrics from collapsing WordPress paragraph word gaps or
shrinking vertical styled-span bboxes.

Source-truth boundary: the no-GPU markerPDF lane maps the searchable-PDF text
extraction path used before upstream pdftext/renderer/model handoff. CID font
width metrics are native parser data; malformed range rows must not drive
visible text grouping.

## Evidence

Status delta:

- `phpPass`: `3575` -> `3577`
- `wordpressScenarios`: `2887` -> `2888`
- `suiteProgress`: `2188` -> `2190`

Red-first:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects overflowing CIDFont W range upper bounds before horizontal advance grouping on current base
Expected: ['Wide Block']
Actual: ['WideBlock']
FAIL rejects overflowing CIDFont W2 range upper bounds before vertical advance grouping on current base
Expected bboxes: [[0.0,0.0,12.0,48.0],[12.0,0.0,24.0,72.0]]
Actual bboxes: [[0.0,0.0,12.0,12.0],[12.0,0.0,24.0,18.0]]
1 test files, 7 assertions, 2 failures
```

Green:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects overflowing CIDFont W range upper bounds before horizontal advance grouping on current base
PASS rejects overflowing CIDFont W2 range upper bounds before vertical advance grouping on current base
1 test files, 22 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-overflow-cid-range-currentbase.php
```

The smoke exits `0` with `overflow_w_range_rejected=true`,
`fallback_advance_gap_preserved=true`, `font_resource_payload_visible=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Focused family verification:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvance*.php lanes/markerpdf/tests/PdfFontWidthRangeOperandAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthDefaultOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthsVerticalWritingCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSparseOverflowCidRangeSourceWidthCurrentBaseTest.php
Focused test run: 27 selected test files (root lock skipped)
27 test files, 1203 assertions, 0 failures
```

Required checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfFontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfFontWidthAdvanceOverflowCidRangeBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-overflow-cid-range-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-font-width-advance-overflow-cid-range-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
lane-status json ok

git diff --check -- lanes/markerpdf
```

## Non-overlap

This does not overlap the accepted no-Length post-CCITT stream-boundary slice,
duplicate `/DW`, malformed `/W2` scalar/array rows, simple-font width range
operands, or CMap source-width fallback slices. It is limited to CIDFont
range upper-bound overflow in `/W` and `/W2`.

## Blocker And Next Task

No-GPU markerPDF scope remains the only blocker: live OCR/model execution,
Surya/Texify/Torch parity, PDFium/PIL, Python multiprocessing, and external PDF
tools remain intentionally excluded. Next work should stay on native
searchable-PDF parser behavior such as fonts, CMaps, stream filters, xref
repair, metadata, outlines, annotations, forms, page geometry, image/filter
metadata, and supplied-boundary table or equation handoffs.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP
PDF parser, font metric extraction, ToUnicode/CMap decoding, styled-span
grouping, and WordPress smoke harness. GPU/model OCR, PDFium/PIL, Python,
multiprocessing, and external PDF tools remain intentionally out of scope.
