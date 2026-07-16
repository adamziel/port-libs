# markerpdf malformed CMap bfrange scalar target sequence current base

Slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T225349Z`

Base accepted HEAD: `79f9f98965689b71a99ad50e1ab3f41478685bb2`

## Source Truth

Pinned upstream markerPDF (`sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes searchable PDF text through parser-backed PDF text/CMap decoding before OCR or model fallbacks. In the no-GPU PHP lane, filtered ToUnicode CMap streams must reject malformed mappings before they replace fallback searchable text for WordPress import.

PDF ToUnicode scalar `beginbfrange` targets are incremented for each source in the range. The native parser already validated the first scalar target and validated every target in array-form ranges. This slice applies the same fail-closed boundary to the incremented scalar target sequence.

## Behavior

A filtered CMap row like this has a well-formed first scalar target but an invalid second target after increment:

```text
1 beginbfrange
<0041> <0042> <D7FF>
endbfrange
```

Before the fix, the first source emitted the pre-surrogate scalar and the second emitted replacement text from the surrogate increment. After the fix, the whole malformed scalar range is ignored, preserving fallback searchable text `AB`. The same sequence guard covers even-byte literal scalar targets while preserving accepted one-byte literal bfrange ranges such as `(W)` to `WXYZ`.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeScalarTargetSequenceFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Notice: iconv(): Detected an incomplete multibyte character ...
FAIL rejects malformed scalar bfrange target sequences before current-base text extraction
Expected: array (0 => 'AB')
Actual: malformed scalar plus replacement text
1 test files, 1 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor::parseToUnicodeRanges()` now computes the mapped source count for scalar `bfrange` rows and validates the incremented target sequence before removing fallback mappings or adding the row to lazy range metadata.
- `cMapScalarRangeTargetSequenceIsWellFormed()` validates hex targets with the existing UTF-16BE scalar guard and literal targets with the existing literal-target guard.
- Lazy `toUnicodeRangeTextForSourceKey()` lookup now revalidates incremented scalar range targets and rejects fixed-width wraparound before decoding.

## Verification

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeScalarTargetSequenceFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed hex scalar bfrange target sequences before current-base text extraction
PASS rejects malformed literal scalar bfrange target sequences before current-base text extraction

1 test files, 84 assertions, 0 failures
```

Adjacent CMap target/range regression set:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeScalarTargetSequenceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayMalformedTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapLiteralTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedBfrangeTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSparseArrayBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php
11 test files, 549 assertions, 0 failures
```

Broad CMap filter family sanity check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeScalarTargetSequenceFilterBoundaryCurrentBaseTest.php
2 test files, 1637 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfrange-scalar-target-sequence-currentbase.php --self-test
```

Result: exits `0` and reports `self_test_passed=true`, `safe_text_preserved=true`, `malformed_scalar_target_sequence_rejected=true`, `decoded_cmap_count=1`, `filters=["FlateDecode"]`, `filter_operand_policy=filters_resolved`, `filter_decode_policy=filter_decoders_resolved`, `decodeparms_operand_policy=decodeparms_resolved`, `decoded_with_current_operands=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeScalarTargetSequenceFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfrange-scalar-target-sequence-currentbase.php
```

All three report no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap work for scalar or array `/Filter` operands, post-`/Length` operands, duplicate `/Filter`, duplicate `/DecodeParms`, duplicate `/Length`, escaped filter names, stale/free/indirect filter owners, unsupported/Crypt filters, DecodeParms fail-closed behavior, explicit filter EOD enforcement, post-`endcmap` exclusion, literal/array/procedure operator decoys, missing/underdeclared row counts, malformed initial `bfchar`/`bfrange` targets, malformed entries inside bfrange target arrays, short or overlong bfrange target arrays, valid one-byte literal scalar bfrange targets, same-width codespace rejection, UseCMap inheritance, Type0 Encoding CID row parsing, xref repair, image/filter metadata, annotations/forms/security, OCR/model handoffs, or supplied-boundary table/equation work.

The bounded behavior is only incremented scalar target validation for otherwise decodable filtered ToUnicode `beginbfrange` rows before native CMap replacement and WordPress paragraph import.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF stream decoder, CMap parser, Unicode/literal target validators, source fallback path, focused test harness, and WordPress smoke renderer. OCR, Surya/Texify/Torch, PDFium/PIL rendering, GPU/model execution, live services, and external PDF tools remain intentionally out of scope.

Next useful markerPDF work: continue non-overlapping native searchable-PDF behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
