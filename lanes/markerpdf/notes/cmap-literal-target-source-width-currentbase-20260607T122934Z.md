# CMap Literal Target Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260607T122934Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260607T122934Z`
Accepted base: `e57c0bcf9b6e3ffa5b25f24a078d7756e1f0a24a`

## Source Truth

`lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json` pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. This slice stays inside the current no-GPU markerPDF scope and only ports the native searchable-PDF CMap/font-width parser boundary.

PDF ToUnicode CMap destinations are strings. Hex-string destinations such as `<0057>` were already supported, but literal-string destinations such as `(W)` and bfrange arrays like `[(T) (h)]` were dropped by the native PHP parser. That loses searchable text and also prevents Type0 source-code boundaries from feeding CIDFont width grouping before WordPress paragraph spacing.

## Behavior

`PdfTextExtractor` now tokenizes literal-string CMap row targets separately from literal-string decoys:

- `beginbfchar` accepts `<source> (literal)` rows.
- `beginbfrange` accepts scalar literal targets and literal entries inside target arrays.
- literal target bytes are decoded through the existing CMap Unicode path, so one-byte literals like `(W)` and escaped UTF-16BE literal bytes share the same downstream handling as hex string destinations.

The existing literal/dictionary/comment operator-boundary protections remain in place; adjacent malformed CMap filter tests still pass.

## Evidence

Red-first after adding the focused test, before the parser fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php
Result: 1 test files, 3 assertions, 3 failures
Failure: expected ["Wide Thin"], actual [" !\"#"]
```

Focused after the fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php
Result: 1 test files, 36 assertions, 0 failures
```

Adjacent CMap source-width and malformed-filter regression run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLiteralTargetSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapDeclaredCountFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapRowTailFilterBoundaryCurrentBaseTest.php
Result: 6 test files, 2151 assertions, 0 failures
```

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2861 -> 2864`
- `wordpressScenarios`: `2397 -> 2398`
- Mapped upstream denominator: unchanged

## Non-Overlap

This slice does not repeat CID target-tail rejection, array-wrapped CID decoys, plus or negative declared counts, high/large CID ranges, invalid/overflow CID targets, notdef rows/chars/ranges, bytewise codespace membership, late `usecmap`, large lazy hex `bfrange` rows, malformed CMap filter boundaries, Type3 fonts, xref repair, stream filters, annotations, forms, metadata, images, OCR/model execution, or supplied table/equation handoffs.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PDF object scanner, stream decoder, CMap parser, CIDFont width lookup, styled text bbox builder, and WordPress smoke path. GPU/model/OCR execution, PDFium, and external PDF tools remain out of scope under the current markerPDF lane rules.
