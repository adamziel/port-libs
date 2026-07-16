# markerPDF malformed object-valued UseCMap CMapName filter boundary

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T173312Z`
- Accepted base: `64b82f4c632a47629c1956efbd5e1b330bb1f53c`
- Source truth: pinned upstream markerPDF `sddai/markerPDF@da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates searchable-PDF text extraction through low-level PDF/CMap parsing before Markdown emission. Native PHP must therefore resolve CMap stream `/Filter` boundaries before treating a stream dictionary or decoded body as a named CMap resource.

## Implementation

- `PdfTextExtractor::cMapNameFromObjectBody()` now decodes the referenced CMap stream with the same fail-closed CMap filter path before accepting dictionary `/CMapName`.
- Stream-body CMap name discovery remains bounded to the first decoded CMap program via `boundedSingleCMapStreamProgram()`.
- `PdfTextExtractor::namedCMapBodies()` now registers names from the parser-bounded decoded CMap body, not the raw decoded stream, so post-`endcmap` names cannot seed `usecmap` inheritance.

## Red-First Evidence

Before the source edit, the new focused regression failed because a malformed `/UseCMap 7 0 R` base stream with missing ASCIIHex EOD advertised `/CMapName /ForgedObjectBase-H`, and the parser inherited a separate valid same-name CMap:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL does not trust dictionary CMapName from malformed object-valued UseCMap streams before current-base text extraction
Expected: ['Object UseCMap Safe Import']
Actual: ['Forged Object UseCMap Leakbject UseCMap Safe Import']
1 test files, 1343 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
1 test files, 1384 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapIndirectScalarFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidUseCMapWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php
6 test files, 1599 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMap*Test.php lanes/markerpdf/tests/PdfParserCMapFilter*Test.php lanes/markerpdf/tests/PdfFont*UseCMap*Test.php lanes/markerpdf/tests/PdfFontCid*UseCMap*Test.php
9 test files, 1711 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-usecmap-filter-boundary-currentbase.php
safe_wordpress_text="Object UseCMap Safe Import"
malformed_usecmap_end_marker_policy="reject_malformed_filter_end_markers"
malformed_usecmap_end_marker_problem="missing_explicit_end_marker"
same_name_valid_cmap_decoded=true
forged_name_not_inherited=true
malformed_payload_excluded=true
executes_python_or_models=false
executes_external_pdf_tools=false
```

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-usecmap-post-name-boundary-currentbase.php
post_end_named_base_excluded_from_usecmap=true
post_end_cmap_name_excluded_from_review=true
visible_text_excludes_cmap_program=true
```

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-usecmap-filter-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-usecmap-filter-boundary-currentbase.php

git diff --check -- lanes/markerpdf
clean
```

## Non-Overlap

This does not repeat accepted malformed CMap filter dictionary/literal operand rejection, selected indirect filter helpers, stale-generation filter owner selection, DecodeParms parameter rejection, null-filter DecodeParms alignment, unsupported/Crypt filter behavior, explicit CMap filter EOD enforcement, or literal-string CMapName/usecmap decoy handling.

The bounded behavior here is specifically object-valued `/UseCMap` name discovery: a malformed filtered base stream cannot advertise a dictionary `/CMapName` or post-`endcmap` decoded `/CMapName` and thereby inherit another same-name valid CMap before WordPress text extraction.

## Dependency Closure

No new support component is needed. This patch reuses the native PHP PDF object scanner, stream filter decoder, CMap boundary parser, named-CMap registry, ToUnicode parser, Identity-H fallback decoder, and WordPress smoke renderer. OCR, GPU/model execution, Python model workers, PDFium/pdftext runtime parity, external PDF tools, and live services were not used.
