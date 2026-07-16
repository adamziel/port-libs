# markerpdf-malformed-cmap-filter-boundary-current-base-20260607T005407Z

Base accepted HEAD: `6842b8783a56f1d4106f7630a35ba63a84799539`.

## Scope

Native no-GPU markerPDF CMap/filter boundary work. This slice covers a filtered
Type0 Encoding CMap whose `begincodespacerange` block contains a malformed
singleton hex source row before a later valid top-level range row:

```text
1 begincodespacerange
<00>
<0000> <FFFF>
endcodespacerange
```

Before this patch the generic top-level hex-pair scanner paired across the row
boundary, then the declared row count truncated away the valid `0000..FFFF`
codespace row. Type0 source-width fallback then split `<00410042>` into raw
bytes and exposed NUL-separated text. The parser now reuses the row-aware CMap
hex-pair recovery path for codespace ranges when malformed singleton rows are
present, so valid later rows remain available before declared-count truncation.

## Evidence

Red-first focused run before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL recovers valid filtered CMap codespace rows after malformed singleton rows before Type0 fallback (lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php)
Expected: array (0 => 'AB')
Actual: array (0 => "\0A\0B")
1 test files, 1 assertions, 1 failures
```

Focused run after the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS recovers valid filtered CMap codespace rows after malformed singleton rows before Type0 fallback
1 test files, 39 assertions, 0 failures
```

Adjacent CMap/filter/source-width family run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapWModeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapOrphanBfcharSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapArrayDecoyCidSourceWidthCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 519 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-singleton-filter-boundary-currentbase.php
```

The smoke emits one paragraph containing `AB` and metadata confirming
`safe_text_preserved=true`, `nul_bytes_excluded=true`,
`cmap_program_text_excluded=true`, `decoded_cmap_count=1`,
`filters=["FlateDecode"]`, `decoded_with_current_operands=true`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceSingletonFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-singleton-filter-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-codespace-singleton-filter-boundary-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lanes/markerpdf/lane-status.json OK\n";'
lanes/markerpdf/lane-status.json OK

git diff --check -- lanes/markerpdf
clean
```

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat the earlier malformed scalar/array/null `/Filter` tails,
malformed DecodeParms, WMode token-boundary recovery, nested CMap array
decoys, indirect `/UseCMap` name recovery, orphan `bfchar` source-width
fallback, Type3 payload exclusion, OCR/model execution, or PDFium execution
slices.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP object
scanner, Flate stream decoder, CMap operator block parser, row-aware hex-pair
scanner, Type0 source-width fallback, and WordPress smoke path. GPU/model/OCR,
PDFium, and external PDF tools remain intentionally out of scope for this
no-GPU markerPDF slice.
