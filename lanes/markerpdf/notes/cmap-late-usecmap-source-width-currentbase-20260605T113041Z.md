# markerPDF CMap late usecmap source-width fallback

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T113041Z`

Accepted base: `5cc1cb8c4d627591b12d77b58e620af0751191d7`

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. This slice remains in the native no-GPU PDF parser scope: searchable-PDF CMap and CIDFont source-width behavior only. The relevant PDF/CMap boundary is that `usecmap` belongs in the CMap prologue before code-space, bfchar/bfrange, notdef, or CID mapping ranges. A `usecmap` token after source/range mappings is malformed and should not inject a base CMap into width grouping.

## Behavior

`PdfTextExtractor::cMapUseCMapNames()` now ignores `usecmap` operators that appear after the first CMap source/range operator. Valid dictionary `/UseCMap` and normal pre-range `usecmap` inheritance still run through the existing prelude path. The new focused fixture proves a malformed late base CMap can no longer narrow the second WordPress-visible span from the fallback default-width geometry.

## Evidence

Red-first focused run before parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores late CMap usecmap after source ranges before source-width fallback on current base
Expected second span bbox [48.0, 0.0, 96.0, 12.0]
Actual second span bbox [48.0, 0.0, 60.0, 12.0]
1 test files, 253 assertions, 1 failures
```

Focused run after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
1 test files, 256 assertions, 0 failures
```

Adjacent CMap/usecmap/font-width regression run after fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidCMapWidthDescendantCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0VerticalUseCMapCidSetCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthCMapResourceCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontToUnicodeSurrogateCidWidthReviewCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
11 test files, 1917 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-late-usecmap-source-width-currentbase.php
late_usecmap_ignored=true
local_cid_widths_preserved=true
late_base_cid_widths_excluded=true
default_width_fallback_applied=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

Lint/status/diff checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-cmap-late-usecmap-source-width-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-late-usecmap-source-width-currentbase.php

php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "markerpdf status json ok\n";'
markerpdf status json ok

git diff --check -- lanes/markerpdf
passed with no output
```

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2-H fallback, CIDFont `/DW` fallback, metric-miss ToUnicode fallback, partial CID-map fallback, horizontal/vertical `TJ` adjustment gap handling, odd-hex padding, repeated zero-padding, explicit longer source-key precedence, malformed mixed-width ToUnicode `bfrange` rejection, valid predefined ToUnicode `usecmap` inheritance, high CID range expansion, notdef range/char fallback, or broad-codespace precedence fixes.

The bounded behavior here is specifically malformed late `usecmap` ordering after CMap source/range rows before CIDFont source-width fallback.

## Dependency Closure

No new support component is needed. The patch reuses the native PDF object parser, stream decoder, CMap scanner, named CMap inventory, ToUnicode/CID map parsers, and CIDFont width grouping paths.
