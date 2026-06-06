# Type3 CharProcs BDC Property Boundary

Micro-slice: `markerpdf-type3-charprocs-boundary-current-base-20260606T095038Z`
Base accepted HEAD: `460c4764cad1ddae97088426a34692370c81dfca`

## Source Truth

The markerPDF no-GPU lane routes searchable PDFs through native PDF text extraction before WordPress block import. Type3 `/CharProcs` are glyph programs: valid `d0`/`d1` metrics may drive spacing, but malformed marked-content setup must not make unsafe glyph metrics authoritative or leak glyph payload text.

PDF marked-content `BDC` takes a tag plus either a property-list dictionary or a property resource name. The existing Type3 CharProc metric parser already applied that property validation to `DP`, but `BDC` only checked that the first operand was a name.

## Red-First Evidence

Added `PdfFontType3CharProcsBdcPropertyBoundaryCurrentBaseTest.php` before the extractor change.

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsBdcPropertyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects numeric Type3 CharProc BDC property operands before WordPress grouping on current base
Values are not identical
Expected: array (
  0 => 'DictOk',
  1 => 'NameOk',
  2 => 'Bad Gap',
)
Actual: array (
  0 => 'DictOk',
  1 => 'NameOk',
  2 => 'BadGap',
)

1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::type3CharProcAllowsPreMetricSetupOperator()` now validates `BDC` operands with `markedContentTagOperand()` and `markedContentPropertyOperand()`, matching the stricter `DP` path.
- Valid `BDC` dictionary and named-property setup still preserves Type3 `d0` widths.
- Numeric `BDC` property operands now fail closed to ordinary `/Widths` fallback before WordPress grouping.

## Verification

Focused new test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsBdcPropertyBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects numeric Type3 CharProc BDC property operands before WordPress grouping on current base

1 test files, 12 assertions, 0 failures
```

Adjacent Type3 marked-content family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3CharProcsBdcPropertyBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentBalanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentPointBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsMarkedContentOperandBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
PASS rejects numeric Type3 CharProc BDC property operands before WordPress grouping on current base
PASS rejects unmatched Type3 CharProc EMC before metrics while preserving balanced marked content on current base
PASS accepts Type3 CharProc metrics after marked-content wrappers before WordPress text grouping on current base
PASS rejects Type3 CharProc metrics after malformed marked-content operands before WordPress grouping on current base
PASS accepts Type3 CharProc MP DP point markers before metrics while rejecting malformed DP operands on current base

5 test files, 51 assertions, 0 failures
```

Broader Type3/font boundary family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfFontType3*Test.php lanes/markerpdf/tests/PdfImageXObjectType3CharProc*Test.php lanes/markerpdf/tests/PdfFontSimpleType3CMapSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapCidType3WidthSpacingBundleCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php
Focused test run: 48 selected test files (root lock skipped)
...
48 test files, 499 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-type3-charprocs-bdc-property-boundary-currentbase.php
```

Emits `DictOk`, `NameOk`, and `Bad Gap` blocks with `valid_bdc_dictionary_property_width_preserved=true`, `valid_bdc_name_property_width_preserved=true`, `numeric_bdc_property_metric_rejected=true`, `charproc_payload_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Type3 direct `d0`/`d1` width handling, CharProc fallback exclusion, exact-generation lookup, indirect `/CharProcs` dictionaries, dictionary streams, direct dictionary tail rejection, comment/reference parsing, Encoding comment/generation parsing, valid marked-content wrappers, malformed extra marked-content operands, marked-content balance, `MP`/`DP` point marker operands, path/text-state/graphics-state setup, compatibility sections, pattern/color operands, resource fallback, soft masks, inline images, FontMatrix vectors, CMaps, CID widths, xref repair, metadata, annotations, forms, image filters, or supplied-boundary table/equation handoffs. It is limited to `BDC` property operand validation before Type3 CharProc metrics are trusted.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, content-token parser, marked-content operand validators, Type3 CharProc width parser, text advance calculator, and WordPress smoke path. GPU/OCR/model execution and external PDF tools remain intentionally out of scope.
