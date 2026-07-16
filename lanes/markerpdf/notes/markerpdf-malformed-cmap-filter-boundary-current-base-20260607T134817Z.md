# markerpdf-malformed-cmap-filter-boundary-current-base-20260607T134817Z

## Scope

- Lane: `markerpdf`
- Accepted base: `0f6a827583ed4cd322d9cb5476a5c5b23c62d765`
- Native no-GPU parser scope only: filtered searchable-PDF ToUnicode CMap parsing.
- Upstream-shaped behavior: CMap `beginbfchar` and `beginbfrange` declared counts are row-count boundaries. If a decoded filtered CMap block declares more rows than it contains, the block is rejected instead of applying a partial mapping that can leak unsafe replacement text.

## Implementation

- `PdfTextExtractor::parseToUnicodeCMap()` now rejects underfilled `bfchar` blocks after filter decoding and row parsing.
- `PdfTextExtractor::parseToUnicodeRanges()` now rejects underfilled `bfrange` blocks after filter decoding and row parsing.
- The row scanners now expose consumed top-level row slots separately from usable mappings, preserving accepted behavior for malformed object, literal-string, and array rows that consume declared-count slots but are not mapped.

## Red-First Evidence

Before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL fails closed on filtered ToUnicode bfchar blocks with fewer rows than the declared count
Actual: array (
  0 => 'Underdeclared Char CMap Leaknderdeclared Char Safe Import',
)
FAIL fails closed on filtered ToUnicode bfrange blocks with fewer rows than the declared count
Actual: array (
  0 => 'Underdeclared Range CMap Leaknderdeclared Range Safe Import',
)
1 test files, 2 assertions, 2 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php
PASS fails closed on filtered ToUnicode bfchar blocks with fewer rows than the declared count
PASS fails closed on filtered ToUnicode bfrange blocks with fewer rows than the declared count
1 test files, 86 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapSingletonObjectDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapScalarFilterValueBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectHelperDecodeParmsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
9 test files, 2246 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-underdeclared-rows-boundary-currentbase.php
exits 0; emits underdeclared_row_block_rejected=true, safe_text_preserved=true, filters=["FlateDecode"], executes_python_or_models=false, executes_external_pdf_tools=false.
```

## Non-Overlap

This slice does not repeat prior malformed CMap filter operand, DecodeParms, overlong bfrange target-array, singleton object row-slot, literal-string, nested-array, or XMP metadata boundaries. It specifically covers valid filtered CMap streams whose declared `bfchar`/`bfrange` row count is not satisfied by decoded top-level rows.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP stream filter decoder, CMap parser, Identity-H fallback extraction, and CMap filter-length owner review. GPU/model/OCR execution, Python, pypdfium, and external PDF tooling remain intentionally out of scope.

## Root Harness

Not run - isolated micro-slice.
