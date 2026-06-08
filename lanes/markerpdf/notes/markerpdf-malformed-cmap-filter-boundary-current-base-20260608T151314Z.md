# markerPDF malformed CMap bfchar target filter boundary

Base accepted HEAD: `74c9c5a40b90a901bc8b54458d1890630664fb6e`.

## Scope

Native no-GPU markerPDF searchable-PDF behavior only. This slice covers a
filtered ToUnicode CMap whose `beginbfchar` row has a malformed scalar hex
target:

```text
2 beginbfchar
<0041> <0058FF>
<0042> <0042>
endbfchar
```

The existing parser already rejected malformed scalar `beginbfrange` hex
targets, but `beginbfchar` applied the target after normal hex normalization.
That could replace safe Identity-H/source fallback text with replacement text
from an incomplete UTF-16BE target. `PdfTextExtractor::parseToUnicodeCMap()`
now validates hex `bfchar` targets as complete CMap Unicode scalar targets
before adding them to the map. Literal targets are preserved.

## Evidence

Focused behavior:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharTargetFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS rejects malformed filtered ToUnicode bfchar scalar hex targets before current-base text extraction

1 test files, 42 assertions, 0 failures
```

Adjacent CMap/filter target family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMalformedBfrangeTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapArrayTargetOperandFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1805 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfchar-target-boundary-currentbase.php
```

The smoke exits 0, renders one `AB` paragraph, and emits
`safe_text_preserved=true`, `malformed_bfchar_target_rejected=true`,
`decoded_cmap_count=1`, `filters=["FlateDecode"]`,
`filter_operand_policy=filters_resolved`,
`executes_python_or_models=false`, and
`executes_external_pdf_tools=false`.

Syntax and JSON checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapBfcharTargetFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapBfcharTargetFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfchar-target-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-bfchar-target-boundary-currentbase.php
```

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted malformed CMap work for scalar/array/literal/
dictionary `/Filter` operands, duplicate `/Filter` or `/DecodeParms`,
stale/free/indirect filter owner boundaries, escaped filter names,
unsupported/Crypt filters, DecodeParms fail-closed behavior, explicit filter
EOD enforcement, post-`endcmap` operator payload exclusion, complete second
CMap program exclusion, literal-string operator decoys, array-contained
`endcmap` tokens, declared-count row slots, singleton object rows, row-tail
handling, short/overlong `bfrange` target arrays, malformed scalar `bfrange`
targets, legal split-row recovery, codespace nested-array handling, same-width
`bfchar` codespace rejection, UseCMap post-name boundaries, Type0 Encoding CID
row parsing, xref repair, image/filter metadata, annotations/forms/security,
OCR/model handoffs, or supplied-boundary table/equation work.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object
scanner, Flate stream decoder, CMap parser, Unicode scalar validation,
source-width fallback path, focused test harness, and WordPress smoke renderer.
GPU/model OCR, PDFium rendering, pypdfium/PIL, Surya, Texify, Torch, live
service workers, and external PDF tools remain intentionally out of scope.
