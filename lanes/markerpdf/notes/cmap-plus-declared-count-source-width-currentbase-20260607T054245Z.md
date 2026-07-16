# markerPDF CMap plus declared-count source-width fallback

Session: `port-dev-markerpdf-source-width-20260607T054245Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260607T054245Z`
Base accepted HEAD: `061d9508a12b92da2c019cd6c353e28f42245284`

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. This no-GPU slice stays in searchable-PDF native parser behavior: Type0 `/Encoding` CMaps map source character codes to descendant CIDs, and CIDFont `/W` widths are keyed by those CIDs before WordPress paragraph-gap and styled-bbox grouping. PDF numeric operands may be explicitly plus-signed, so a CMap declared-count token such as `+4 begincidchar` or `+1 begincidrange` must bound the mapping rows consumed before source-width lookup.

## Behavior

`PdfTextExtractor::cMapDeclaredOperatorCountBefore()` now accepts a delimiter-separated leading `+` before the CMap declared count. It still rejects embedded signs and leaves negative counts unsupported/fail-closed. The new fixtures prove surplus CMap rows after the declared count do not leak into source-width grouping:

- `+4 begincidchar` followed by eight rows must only map the first four source bytes to narrow CIDs.
- `+1 begincidrange` followed by two ranges must only map the first range.

In both cases, visible text remains `ABCD EFGH`, but the second run uses default width fallback instead of the surplus wide CID rows, so its styled bbox is `[12,0,36,12]` rather than `[12,0,60,12]`.

## Focused Evidence

Red-first before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL honors plus-signed CMap cidchar declared counts before source-width fallback on current base
FAIL honors plus-signed CMap cidrange declared counts before source-width fallback on current base
1 test files, 14 assertions, 2 failures
```

After the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS honors plus-signed CMap cidchar declared counts before source-width fallback on current base
PASS honors plus-signed CMap cidrange declared counts before source-width fallback on current base
1 test files, 22 assertions, 0 failures
```

Adjacent CMap/font source-width family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)
Focused test run: 26 selected test files (root lock skipped)
26 test files, 656 assertions, 0 failures
```

Syntax and smoke evidence:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-cmap-plus-declared-count-source-width-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-plus-declared-count-source-width-currentbase.php

php lanes/markerpdf/examples/wordpress-pdf-cmap-plus-declared-count-source-width-currentbase.php
emits Gutenberg paragraph ABCD EFGH with plus_declared_count_honored=true,
surplus_cmap_rows_excluded_from_widths=true, visible_text_imported=true,
cmap_program_bytes_visible_text_excluded=true, and no Python/model/OCR or external PDF execution.
```

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H/UCS2 source widths, metric-miss fallback, high/large CID range expansion, sparse codespace ordering, lazy ToUnicode bfrange lookup, CMap cidchar/cidrange order precedence, notdef range/char fallback, malformed broad codespace recovery, array-wrapped CMap decoy rejection, invalid later CID range rejection, source-width TJ gaps, indirect CIDFont widths, vertical `/W2`, Type3 widths, xref repair, stream filters, annotations, forms, metadata, images, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically plus-signed CMap declared counts before Type0 Encoding CMap source-CID mapping and CIDFont source-width fallback.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, content stream tokenizer, CMap block parser, declared-count boundary logic, CIDFont width lookup, styled-text bbox builder, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, pypdfium rendering, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU directive.
