# markerpdf malformed CMap filter boundary current-base 2026-06-07T074049Z

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260607T074049Z`
- Accepted base: `3d2d3e6ef4226dffa58dcb186275876022069cff`
- Native no-GPU scope only: searchable-PDF text extraction, filtered CMap parsing, and WordPress import smoke. No OCR, model execution, PDF action execution, or external PDF tools.

## Source Truth

PDF ToUnicode `beginbfrange` array rows carry one destination string per source code in the source range. The existing current-base parser already failed closed for short target arrays, nested row tails, and malformed filtered CMap operands. This slice covers the opposite cardinality boundary: a filtered CMap row with more array targets than mapped source codes. The native parser now requires exact target cardinality before applying the row; otherwise it preserves Type0 fallback text.

## Red-First Evidence

Before the parser guard:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects overlong filtered ToUnicode bfrange target arrays before current-base text extraction (lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php)
Values are not identical
Expected: array (
  0 => 'Array Target Safe Import',
)
Actual: array (
  0 => 'Array Target CMap Leakrray Target Safe Import',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::parseToUnicodeRanges()` now rejects `bfrange` array-target rows unless the number of target strings exactly matches the number of mapped source codes.
- Added `PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php` to exercise a compressed `/FlateDecode` ToUnicode CMap whose singleton source range has two array targets.
- Added `wordpress-pdf-malformed-cmap-bfrange-array-target-boundary-currentbase.php` to prove the WordPress paragraph import keeps the safe fallback text and excludes rejected CMap target strings.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects overlong filtered ToUnicode bfrange target arrays before current-base text extraction

1 test files, 41 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 1722 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfrange-array-target-boundary-currentbase.php
exits 0; emits safe paragraph `Array Target Safe Import` with overlong_target_array_rejected=true, safe_text_preserved=true, executes_python_or_models=false, and executes_external_pdf_tools=false.
```

## Non-Overlap

This does not repeat earlier malformed CMap filter-boundary slices for scalar or indirect `/Filter` operands, `/DecodeParms`, duplicate `/Filter` and `/DecodeParms`, escaped filter names, `UseCMap`, WMode, codespace row tails, short bfrange target arrays, singleton malformed rows, or nested array row tails. The new boundary is specifically an overlong `beginbfrange` target array inside a successfully decoded filtered ToUnicode stream.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP stream filter decoding and CMap parser; it does not require GPU/model dependencies, OCR, PDFium, Python worker execution, online services, or external PDF tools.

## Next Task

Continue non-overlapping native markerPDF parser work around CMaps/font encodings, stream filter metadata, xref repair, page geometry, annotations/forms/security preflight, image metadata, and supplied-boundary table or equation handoffs.
