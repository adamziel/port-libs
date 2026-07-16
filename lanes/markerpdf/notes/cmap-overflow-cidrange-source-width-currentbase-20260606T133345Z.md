# markerPDF CMap overflow CID range source-width fallback

Session: `port-dev-markerpdf-source-width-20260606T133345Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T133345Z`

Base accepted HEAD: `5b6122b8531cb9888e3096ea4eb4faa04a0af79a`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction through pdftext/PDFium font and CMap decoding before layout grouping. In this no-GPU native PHP lane, Type0 Encoding CMaps are the local source for source-code to CID mapping, and descendant CIDFont `/W` or `/DW` metrics plus text-state `Tw` use those CIDs before WordPress paragraph/styled-span output.

PDF CIDs are bounded to `0..0xffff`. A malformed sequential `begincidrange` whose starting CID is valid but whose later target CIDs overflow that space should be ignored as a whole; it should not partially replace an earlier valid source-to-CID range before source-width fallback.

## Behavior

`PdfTextExtractor::parseCidRanges()` now validates the mapped sequential target CID span before removing existing source mappings or appending lazy `cidRanges` metadata. Ranges that would overflow the valid CID space are skipped.

The focused fixture uses:

- a Type0 Encoding CMap with a valid `begincidrange <1000> <1003> 32`;
- a later malformed `begincidrange <1000> <1003> 65534`, which would overflow after two mapped source codes;
- descendant CIDFont `/W [32 35 1000 65534 65535 250]`;
- `24 Tw` so the first source code mapped to CID 32 proves word-spacing source evidence survived;
- ToUnicode rows that decode the visible text as `ABCD`.

Before the fix, the red-first focused run decoded `ABCD` but produced styled bbox `[0,0,30,12]`, showing the later overflow range had partially overridden the earlier valid mapping and selected narrow overflow CID widths. After the fix, the styled bbox is `[0,0,72,12]`.

## Verification

- Red-first before source edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  - `1 test files, 384 assertions, 1 failures`
  - failure: expected bbox `[0.0, 0.0, 72.0, 12.0]`, actual `[0.0, 0.0, 30.0, 12.0]`
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - no syntax errors
- `php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-overflow-cidrange-source-width-currentbase.php`
  - no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  - `1 test files, 388 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLazyBfrangeZeroPaddedSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapShortBfrangeArraySourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNotdefCharSourceWidthCurrentBaseTest.php`
  - `8 test files, 480 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-overflow-cidrange-source-width-currentbase.php`
  - emits `overflow_later_cidrange_ignored=true`, `visible_text_imported=true`, `false_decoded_word_gap_excluded=true`, `partial_overflow_width_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2560 -> 2561`
- `wordpressScenarios`: `2173 -> 2174`
- Focused behavior delta: 1 new focused PASS case with 10 direct assertions in `PdfCMapSourceWidthFallbackCurrentBaseTest.php`
- Mapped upstream denominator unchanged; this is additive native PHP coverage inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted invalid starting-CID rejection, zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss fallback, high/large CID range expansion, sparse or multi-range codespace ranking, late valid CID range/source row ordering, notdef range/char semantics, bytewise codespace membership, ToUnicode bfrange array fallback, vertical `/W2`, indirect width operands, Type3 widths, xref repair, stream filters, metadata, annotations, forms, image/filter review, OCR, or model execution.

The bounded behavior is only later sequential Encoding CMap CID ranges whose target CIDs overflow after a valid starting CID, preserving earlier valid source-CID mappings before source-width fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, Type0 Encoding CMap source segmentation, CIDFont width metrics, text-state spacing, styled-span extraction, and WordPress smoke path. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
