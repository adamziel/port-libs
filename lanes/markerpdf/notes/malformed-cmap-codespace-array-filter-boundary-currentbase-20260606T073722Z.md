# markerPDF malformed CMap codespace-array filter boundary

Micro-slice: `markerpdf-malformed-cmap-filter-boundary-current-base-20260606T073722Z`

Base accepted HEAD: `10c8faa2bd4e18ec06eb4850c4a30e46d6ded63d`

## Source Truth

Pinned upstream markerPDF `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through PDF parser/font decoding before Markdown assembly. Under the current no-GPU lane scope, the native PHP parser owns the ToUnicode CMap boundary: decoded filtered CMap program bytes may be review metadata, but malformed nested CMap syntax must not alter WordPress-visible source-code segmentation.

## Behavior

`PdfTextExtractor::parseCMapCodeSpaceRanges()` now reads `begincodespacerange` rows with the existing token-aware top-level hex scanner instead of a raw regex. Hex pairs inside nested arrays are ignored, matching the parser boundary already used for literal strings, dictionaries, comments, and malformed nested CMap mapping rows.

The focused fixture uses a FlateDecode ToUnicode CMap where a nested array forges a broad codespace:

```text
2 begincodespacerange
[<0000> <FFFF>]
<00AA> <00AA>
endcodespacerange
1 beginbfrange
<004E> <004E> <...Nested Codespace CMap Leak...>
endbfrange
```

Before the fix, the nested array was treated as a valid top-level codespace, so the first source code was remapped and visible text became `Nested Codespace CMap Leakested Codespace Safe Import`. After the fix, only the real top-level `<00AA> <00AA>` codespace is considered; the malformed range does not apply and WordPress-visible text remains `Nested Codespace Safe Import`.

## Evidence

Red-first focused run after adding the test and before the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores nested codespace arrays in filtered CMaps before ToUnicode source matching
Expected: array (
  0 => 'Nested Codespace Safe Import',
)
Actual: array (
  0 => 'Nested Codespace CMap Leakested Codespace Safe Import',
)

1 test files, 1 assertions, 1 failures
```

Focused run after the source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores nested codespace arrays in filtered CMaps before ToUnicode source matching

1 test files, 37 assertions, 0 failures
```

Adjacent CMap/filter/source-width run:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfParserMalformedCMapCodespaceArrayFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfParserCMapFilterEodBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserMalformedCMapNullFilterLengthBoundaryCurrentBaseTest.php
Focused test run: 5 selected test files (root lock skipped)
5 test files, 2064 assertions, 0 failures
```

Broader CMap and DecodeParms family:

```text
php tools/run-tests.php lanes/markerpdf/tests/Pdf*CMap*Test.php lanes/markerpdf/tests/PdfParser*DecodeParms*Test.php
Focused test run: 40 selected test files (root lock skipped)
40 test files, 3057 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-malformed-cmap-codespace-array-currentbase.php
```

The smoke emits `safe_text_imported=true`, `nested_codespace_decoy_excluded=true`, `decoded_cmap_count=1`, `filter_operand_policy="filters_resolved"`, `filter_decode_policy="filter_decoders_resolved"`, `decoded_with_current_operands=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, with one paragraph: `WP Nested Codespace Safe Import`.

Root harness status: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted malformed CMap `/Filter` operands, extra filter tails, duplicate filter declarations, indirect scalar filter owners, unsupported/Crypt filters, malformed DecodeParms, missing explicit filter EOD, post-`endcmap` cleanup, complete second-program exclusion, literal-string `CMapName`/`usecmap` decoys, nested `bfchar` arrays, nested `bfrange` target arrays, late `usecmap`, malformed broad top-level codespaces, or CID width/source-width fallback work.

The bounded behavior is only nested arrays inside decoded filtered ToUnicode CMap `begincodespacerange` blocks before source matching and WordPress paragraph extraction.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream filter decoder, CMap stream decoder/parser, token-aware CMap literal/dictionary/array skipping, ToUnicode source matching, and WordPress smoke renderer. Full upstream OCR/model/PDFium parity, live OCR/layout/table/equation models, raster rendering, and exact GPU/model benchmark parity remain intentionally out of scope under the current markerPDF no-GPU directive.
