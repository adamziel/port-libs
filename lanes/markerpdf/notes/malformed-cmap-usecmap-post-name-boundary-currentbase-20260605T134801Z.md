# markerpdf malformed CMap usecmap post-name boundary current-base

## Scope

- Lane: `markerpdf`
- Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260605T134801Z`
- Accepted base: `09789c95d9b9938ab902a637c46d97251cf0b7ee`
- Source truth: PDF CMap programs terminate at the first `endcmap` for native parser import. A `/CMapName` that appears only after that boundary must not register a named base CMap for later `usecmap` inheritance.

## Implementation

- `PdfTextExtractor::namedCMapBodies()` now registers named CMap resources from the parser-bounded decoded CMap program instead of the raw decoded stream body.
- ToUnicode CMap recursion-cycle naming and indirect `/UseCMap` body-name lookup now use parser-bounded CMap bodies.
- CMap stream filter/length/owner review metadata reports `cmap_name` from the parser-bounded body, so a post-`endcmap` `/CMapName` is not surfaced as a valid stream name.

## Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php`
  - `No syntax errors detected in lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-usecmap-post-name-boundary-currentbase.php`
  - `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-usecmap-post-name-boundary-currentbase.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php`
  - `1 test files, 58 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapUseCMapPostNameBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterOwnerStreamLengthCurrentBaseTest.php`
  - `5 test files, 1360 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-usecmap-post-name-boundary-currentbase.php`
  - emits `post_end_named_base_excluded_from_usecmap=true`, `post_end_cmap_name_excluded_from_review=true`, `visible_text_excludes_cmap_program=true`, and `<p>Safe Import</p>`.

## Non-overlap

This slice avoids the accepted annotation-link widget parent generation work and the existing CMap filter operand/EOD/null-filter boundary clusters. It adds the narrower named-base `usecmap` boundary where a filtered base CMap has no valid pre-boundary `/CMapName`.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object scanner, stream filter decoder, parser-bounded CMap program extraction, usecmap parser, text extractor, and WordPress smoke renderer. No OCR, GPU/model execution, Python model workers, external PDF tools, or live services were used.
