# markerPDF Font Width Advance Duplicate DW Boundary

Session: `port-dev-markerpdf-font-width-advance-20260608T135027Z`
Micro-slice: `markerpdf-font-width-advance-boundary-current-base-20260608T135027Z`
Base accepted HEAD: `866acb3705c41894d861c1038e1d10801bbc0d5b`

## Source Truth

Upstream markerPDF delegates searchable PDF text extraction to PDF parser/pdftext
layers before OCR or model execution. In the native no-GPU scope, CIDFont
default advance widths are parser metrics. Duplicate top-level font metric keys
must follow the selected current dictionary value used by adjacent accepted
font-width behavior, while malformed tailed scalar operands still fail closed.

This slice does not repeat simple-font duplicate `/Widths`, valid/tailed CIDFont
`/DW` operands, indirect `/W` or `/W2` arrays, Type3 `FontMatrix`, text-state
spacing, TJ/Tz/Td/Tm advance guards, CMap source-width fallback, OCR/model
execution, or raster rendering. The bounded behavior is only duplicate top-level
CIDFont `/DW` default-width keys before text-advance gap decisions.

## Red First

Before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceDuplicateDwBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL uses current duplicate CIDFont DW default width before text-advance gaps on current base
Values are not identical
Expected: array (
  0 => 'Wide Block',
)
Actual: array (
  0 => 'WideBlock',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::fontWidthMetrics()` now resolves CIDFont `/DW` through a
  last-value scalar helper, matching current duplicate font metric selection
  while preserving the existing malformed-tail rejection.
- Added `topLevelPdfLastSingleNumberValueAfterNameResolvingObjects()` for
  object-aware selected-last scalar metric operands.
- Added a focused test fixture where `/DW 1000 /DW 250` must select the current
  `250` unit default width so WordPress paragraph extraction emits
  `Wide Block` rather than stale `WideBlock`.
- Added a WordPress smoke that emits review metadata and a paragraph block
  without invoking Python, OCR, models, raster renderers, or external PDF tools.

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidthAdvanceDuplicateDwBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS uses current duplicate CIDFont DW default width before text-advance gaps on current base

1 test files, 11 assertions, 0 failures
```

Adjacent font-width family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontWidth*CurrentBaseTest.php lanes/markerpdf/tests/PdfFontMalformedWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontDescriptorMissingWidthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0DescriptorWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CMapDescriptorWidthCurrentBaseTest.php
Focused test run: 19 selected test files (root lock skipped)
19 test files, 1036 assertions, 0 failures
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfFontWidthAdvanceDuplicateDwBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-font-width-advance-duplicate-dw-currentbase.php
```

All changed PHP files reported no syntax errors.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-font-width-advance-duplicate-dw-currentbase.php
```

The smoke exits `0` and emits `duplicate_dw_current_default_selected=true`,
`stale_first_dw_false_join_excluded=true`,
`styled_span_bboxes_preserved=true`, `executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, top-level dictionary value parser, object-aware scalar resolver,
CIDFont width metric extraction, Type0 ToUnicode text grouping, focused test
harness, and WordPress smoke renderer. GPU/model OCR, PDFium rendering,
pypdfium/PIL, Surya, Texify, Torch, live-service workers, and external PDF tools
remain intentionally out of scope under the markerPDF no-GPU directive.

## Next Task

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser
behavior around CMaps, xref repair, stream filters, metadata, annotations,
forms, page geometry, image/filter metadata, and supplied-boundary table or
equation handoffs.
