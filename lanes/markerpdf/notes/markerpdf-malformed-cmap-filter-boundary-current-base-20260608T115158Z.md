# markerPDF malformed CMap missing declared-count filter boundary

Session: `port-dev-markerpdf-malformed-cmap-20260608T115158Z`
Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260608T115158Z`
Accepted base: `ef204610238d00e07d53becb139e28941de74b31`

## Source Truth

`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` pins upstream `sddai/markerPDF` at
`da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. This no-GPU markerPDF slice stays
inside native searchable-PDF parsing. PDF CMap `beginbfchar` and `beginbfrange`
operators require an integer row-count operand; malformed or absent count
operands must not turn a filtered ToUnicode block into an unbounded mapping
source before WordPress import.

## Behavior

Before this patch, successfully decoded filtered ToUnicode CMap streams applied
mapping rows even when `beginbfchar` or `beginbfrange` had no declared count, or
when the preceding operand was a name, array, or boolean. Invalid numeric counts
such as floats and negative integers already failed closed; this patch closes
the adjacent missing/non-integer operand boundary.

`PdfTextExtractor::cMapToUnicodeMappingBlocks()` now emits ToUnicode mapping
blocks only when `cMapDeclaredOperatorCountBefore()` returns an integer count.
The stream still decodes and review metadata still records resolved
`/FlateDecode` filter handling, but malformed uncounted mapping rows are ignored
before text extraction.

## Evidence

Red-first after adding the focused test, before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapMissingDeclaredCountFilterBoundaryCurrentBaseTest.php
Result: 1 test files, 2 assertions, 2 failures
Failure: missing-count bfchar and bfrange CMap rows leaked replacement text.
```

Focused after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapMissingDeclaredCountFilterBoundaryCurrentBaseTest.php
Result: 1 test files, 344 assertions, 0 failures
```

Adjacent CMap declared-count/filter regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapMissingDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapUnderdeclaredRowsFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapSplitRowFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfcharCodespaceFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapBfrangeArrayTargetFilterBoundaryCurrentBaseTest.php
Result: 7 test files, 748 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-missing-declared-count-currentbase.php
Result: exits 0 and emits safe paragraphs for missing-count bfchar and non-integer bfrange operands, with decoded CMap filter review metadata and no Python/model/external-tool execution.
```

Syntax:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfParserMalformedCMapMissingDeclaredCountFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-missing-declared-count-currentbase.php
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `3078 -> 3080`
- `wordpressScenarios`: `2540 -> 2541`
- Mapped upstream denominator: unchanged

## Non-Overlap

This slice does not repeat accepted scalar or array `/Filter` operand rejection,
post-`/Length` operands, duplicate `/Filter` or `/DecodeParms`, escaped filter
names/keys, UseCMap inheritance, WMode, underdeclared CMap row counts, malformed
row slot accounting, legal split-row recovery, literal-string operator decoys,
short/overlong `bfrange` target arrays, same-width codespace rejection,
vertical-tab row whitespace, Type0 Encoding CID declared-count source-width
work, xref repair, image/filter metadata, annotations/forms/security, OCR/model
handoffs, or supplied-boundary table/equation work.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF object
scanner, stream filter decoder, CMap parser, ToUnicode text extraction path,
review metadata, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch,
PDFium/PIL rendering, table/equation models, external PDF tools, and upstream
model benchmark parity remain intentionally out of scope under the current
markerPDF no-GPU rules.
