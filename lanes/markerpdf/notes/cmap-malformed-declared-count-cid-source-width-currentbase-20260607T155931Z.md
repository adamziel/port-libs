# markerPDF Malformed Declared-Count CID CMap Source Width Current Base

Session: `port-dev-markerpdf-source-width-20260607T155931Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260607T155931Z`

Base accepted HEAD: `4fa012f593053e9172158b015d96f6a54032a32d`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the pdftext/PDF parser boundary before Marker assembles spans, lines, blocks, and Markdown.
- The native PHP fallback must preserve text source-code boundaries and CIDFont metrics before WordPress paragraph grouping when pdftext, PDFium, Python, OCR/model workers, and external PDF tools are unavailable.
- This slice is bounded to Type0 Encoding CMap source-to-CID parsing. Declared CMap row counts are slot boundaries: malformed `begincidchar` and `begincidrange` rows inside the declared count consume row slots, so valid-looking decoy rows after the count cannot override the CID source-width mapping used for glyph advance.

## Behavior Added

`PdfTextExtractor::parseCidCMap()` now passes raw `begincidchar` and `begincidrange` block bodies into CID row parsers so malformed rows are visible to row-slot accounting. The CID char/range parsers now tokenize top-level hex, integer, array, dictionary, and other PDF values by row, consume malformed declared-count rows, and only apply well-formed rows within the declared count.

Array-wrapped CID decoys remain ignored without consuming declared slots, preserving the accepted array-decoy source-width behavior.

## Focused Fixture

`PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php` adds two Type0 Encoding CMap fixtures:

- a declared-count `begincidchar` block with a malformed dictionary row followed by a decoy `<10> 40` row outside the declared count;
- a declared-count `begincidrange` block with a malformed dictionary row followed by a decoy `<10> <13> 40` row outside the declared count.

Both fixtures first map `<10>` through `<13>` to CID 60..63 with narrow `/W` metrics, then use a second positioned text run. Correct source-width grouping preserves `Wide Thin`; accepting the decoy CID 40..43 widths collapses the paragraph to `WideThin`.

## Evidence

Red-first focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL consumes malformed declared-count CMap cidchar row slots before source-width fallback on current base
Expected: array (0 => 'Wide Thin',)
Actual: array (0 => 'WideThin',)
FAIL consumes malformed declared-count CMap cidrange row slots before source-width fallback on current base
Expected: array (0 => 'Wide Thin',)
Actual: array (0 => 'WideThin',)
1 test files, 2 assertions, 2 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS consumes malformed declared-count CMap cidchar row slots before source-width fallback on current base
PASS consumes malformed declared-count CMap cidrange row slots before source-width fallback on current base
1 test files, 22 assertions, 0 failures
```

Adjacent regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapMalformedDeclaredCountCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapArrayDecoyCidSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapPlusDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNegativeDeclaredCountSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapCidTargetTailSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 128 assertions, 0 failures
```

Broader CMap/font source-width family:

```text
php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)
Focused test run: 30 selected test files (root lock skipped)
30 test files, 756 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-malformed-declared-count-cid-source-width-currentbase.php
```

The smoke exits 0, emits one WordPress paragraph `Wide Thin`, and reports `malformed_declared_count_cidchar_slot_consumed=true`, `malformed_declared_count_cidrange_slot_consumed=true`, `decoy_cid_rows_excluded_from_widths=true`, `false_join_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`, `executes_python_pdftext=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `2908 -> 2910`
- `wordpressScenarios`: `2425 -> 2426`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped CMap/font source-width boundary.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted plus/negative declared counts, array-wrapped CID decoys, malformed CID target tails, ToUnicode malformed declared-count filters, orphan ToUnicode `bfchar`, high/large/lazy ranges, notdef rows, bytewise codespace handling, late `usecmap`, Type3 font boundaries, xref repair, stream filters, annotations, forms, metadata, images, OCR, or model work. The new boundary is specifically malformed declared-count Type0 Encoding CMap CID row slots before source-width fallback.

## Dependency Closure

No new support component is needed. This slice reuses the native PDF object scanner, stream decoder, CMap tokenizer/block parser, CIDFont width parser, text-positioning path, styled-span extraction path, and WordPress smoke renderer. OCR/model/PDFium parity remains intentionally out of scope under the current no-GPU markerPDF direction.
