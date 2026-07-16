# markerPDF malformed CMap literal target filter boundary

Session: `port-dev-markerpdf-malformed-cmap-20260608T201630Z`
Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T201630Z`
Accepted base: `94d7cef270e305ef6fc0f67053ec55d96bb371c3`

## Source Truth

Pinned upstream markerPDF (`sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`) routes searchable PDF text through pdftext/PDFium before OCR/model fallbacks. Under the current no-GPU lane scope, this native PHP port owns the searchable-PDF parser boundary for stream filters, ToUnicode CMaps, source-width fallback, and WordPress-visible paragraph text.

PDF ToUnicode CMap targets are byte strings. The accepted native parser already rejects malformed hex string targets before allowing them to replace fallback source bytes. This slice applies the same fail-closed boundary to literal-string targets after a filtered ToUnicode CMap decodes successfully.

## Behavior

A filtered ToUnicode CMap row such as:

```text
1 beginbfchar
<004C> (\330\000)
endbfchar
```

or the equivalent scalar/array `beginbfrange` row is syntactically shaped like a valid CMap mapping, but the literal target bytes decode to the isolated UTF-16BE surrogate `d800`. That target must not replace searchable source text or emit NUL/replacement bytes into WordPress paragraphs.

`PdfTextExtractor::parseToUnicodeCMap()` and `parseToUnicodeRanges()` now reject malformed even-byte literal targets before CMap text replacement. One-byte literal targets still use the existing PDFDocEncoding/source-width behavior, preserving the accepted `PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php` coverage.

## Red-First Evidence

Before the source edit, a one-off focused probe against the current base extracted replacement-plus-NUL text instead of the safe fallback:

```text
PHP Notice: iconv(): Detected an incomplete multibyte character ... PdfTextExtractor.php on line 40872
array (
  0 => 'replacement-plus-NUL + iteral Surrogate Safe Import',
)
```

The expected safe text is:

```text
Literal Surrogate Safe Import
```

## Verification

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapLiteralTargetFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapLiteralTargetFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-literal-target-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-literal-target-currentbase.php
```

Focused test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapLiteralTargetFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed literal ToUnicode bfchar targets after filtered current-base CMap decoding
PASS rejects malformed literal ToUnicode scalar bfrange targets after filtered current-base CMap decoding
PASS rejects malformed literal ToUnicode bfrange array targets after filtered current-base CMap decoding

1 test files, 135 assertions, 0 failures
```

Adjacent target/filter family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapLiteralTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayMalformedTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
...
6 test files, 1849 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-literal-target-currentbase.php --self-test
```

The smoke exits `0` and reports `self_test_passed=true`, `malformed_literal_targets_rejected=true`, `decoded_cmap_count=1` for `bfchar`, scalar `bfrange`, and target-array `bfrange` cases, `filters=["FlateDecode"]`, `filter_operand_policy=filters_resolved`, `filter_decode_policy=filter_decoders_resolved`, `decodeparms_operand_policy=decodeparms_resolved`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Status JSON:

```text
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "json ok\n";'
json ok
```

Whitespace:

```text
git diff --check -- lanes/markerpdf
```

No output.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap work for scalar or array `/Filter` operands, post-`/Length` operands, duplicate `/Filter` or `/DecodeParms`, escaped filter names, stale/free/indirect filter owners, unsupported/Crypt filters, DecodeParms fail-closed behavior, explicit filter EOD enforcement, post-`endcmap` exclusion, literal/array/procedure operator decoys, missing/underdeclared row counts, malformed scalar hex `bfchar` targets, malformed scalar hex `bfrange` targets, malformed hex entries inside `bfrange` target arrays, short or overlong `bfrange` target arrays, array target operand type rejection, same-width `bfchar` codespace rejection, valid one-byte literal target source-width fallback, UseCMap inheritance, Type0 Encoding CID row parsing, xref repair, image/filter metadata, annotations/forms/security, OCR/model handoffs, or supplied-boundary table/equation work.

The bounded behavior is specifically isolated-surrogate literal-string targets in otherwise successfully decoded filtered ToUnicode `bfchar`, scalar `bfrange`, and target-array `bfrange` rows.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, Flate stream decoder, CMap parser, Unicode validation, source fallback path, focused test harness, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, PDFium/PIL rendering, table/equation model inference, external PDF tools, online services, and exact upstream model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU direction.

## Next

Continue native no-GPU markerPDF work on non-overlapping searchable-PDF parser behavior around fonts, CMaps, stream filters, xref repair, metadata, annotations, forms, security preflight, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
