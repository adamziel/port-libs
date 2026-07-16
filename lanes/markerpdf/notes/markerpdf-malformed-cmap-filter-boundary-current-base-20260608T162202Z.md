# markerPDF malformed CMap bfrange array target filter boundary

Session: `port-dev-markerpdf-malformed-cmap-20260608T162202Z`
Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T162202Z`
Accepted base: `bc9489e331853d7b5b38ea37ea420a29310b5ae4`

## Source Truth

Pinned upstream markerPDF (`sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes searchable PDF text through pdftext/PDFium text extraction before OCR/model fallbacks. Under the current no-GPU lane scope, the native PHP fallback owns filtered ToUnicode CMap parsing before WordPress paragraph import.

PDF ToUnicode CMap targets are byte strings. For native no-GPU text extraction, hex string targets in `bfchar` and scalar `bfrange` rows already had to decode as complete UTF-16BE scalar strings before replacing fallback text. This slice applies the same boundary to hex strings inside `beginbfrange` target arrays.

## Behavior

Before this patch, a successfully decoded filtered CMap row such as:

```text
1 beginbfrange
<0041> <0042> [ <0058FF> <0042> ]
endbfrange
```

was accepted because the target array shape and cardinality were valid. The malformed first hex target then decoded into `NUL + X + replacement` and polluted WordPress-visible text.

`PdfTextExtractor::parseToUnicodeRanges()` now rejects a `bfrange` target-array row when any hex array target is not a well-formed CMap Unicode scalar target. Literal array targets remain accepted and are covered by the adjacent literal-target test.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayMalformedTargetFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL rejects malformed filtered ToUnicode bfrange array hex targets before current-base text extraction
Values are not identical
Expected: array (
  0 => 'AB',
)
Actual: array (
  0 => '' . "\0" . 'X�B',
)

1 test files, 1 assertions, 1 failures
```

## Verification

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayMalformedTargetFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed filtered ToUnicode bfrange array hex targets before current-base text extraction

1 test files, 42 assertions, 0 failures
```

Adjacent target/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayMalformedTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedBfrangeTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayTargetOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
...
7 test files, 1806 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfrange-array-malformed-target-currentbase.php
```

The smoke exits `0` and emits `safe_text_preserved=true`, `malformed_array_hex_target_rejected=true`, `decoded_cmap_count=1`, `filters=["FlateDecode"]`, `filter_operand_policy=filters_resolved`, `filter_decode_policy=filter_decoders_resolved`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayMalformedTargetFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfrange-array-malformed-target-currentbase.php
```

All three report no syntax errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap work for scalar or array `/Filter` operands, post-`/Length` operands, duplicate `/Filter` or `/DecodeParms`, escaped filter names, stale/free/indirect filter owners, unsupported/Crypt filters, DecodeParms fail-closed behavior, explicit filter EOD enforcement, post-`endcmap` exclusion, literal/array/procedure operator decoys, missing/underdeclared row counts, malformed scalar `bfchar` targets, malformed scalar `bfrange` targets, short or overlong `bfrange` target arrays, array target operand type rejection, same-width `bfchar` codespace rejection, UseCMap inheritance, Type0 Encoding CID row parsing, xref repair, image/filter metadata, annotations/forms/security, OCR/model handoffs, or supplied-boundary table/equation work.

The bounded behavior is specifically malformed hex entries inside an otherwise correctly shaped and correctly sized filtered ToUnicode `beginbfrange` target array.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, Flate stream decoder, CMap parser, Unicode scalar validation helper, source fallback path, focused test harness, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, PDFium/PIL rendering, table/equation model inference, external PDF tools, online services, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU direction.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
