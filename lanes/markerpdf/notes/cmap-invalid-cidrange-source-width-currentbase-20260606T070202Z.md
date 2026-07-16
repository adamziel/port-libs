# markerPDF CMap invalid CID range source-width fallback

Session: `port-dev-markerpdf-source-width-20260606T070202Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260606T070202Z`

Base accepted HEAD: `14edf96a09146b5955b63623b601e9398fd5b965`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction through pdftext/PDFium font and CMap decoding before layout grouping. In this no-GPU native PHP lane, Type0 Encoding CMaps are the local source for source-code to CID mapping, and descendant CIDFont `/W` or `/DW` metrics plus text-state `Tw` use those CIDs before WordPress paragraph/styled-span output.

PDF CIDs are bounded to `0..0xffff`. A malformed later `begincidrange` whose starting CID is outside that space should not erase an earlier valid source-to-CID range before source-width fallback.

## Behavior

`PdfTextExtractor::parseCidRanges()` now rejects CID ranges with a starting CID above `0xffff` before removing existing mappings or appending lazy `cidRanges` metadata.

The focused fixture uses:

- a Type0 Encoding CMap with a valid `begincidrange <1000> <1003> 32`;
- a later malformed `begincidrange <1000> <1003> 70000`;
- descendant CIDFont `/W [32 35 1000]`;
- `24 Tw` so the first source code mapped to CID 32 proves word-spacing source evidence survived;
- ToUnicode rows that decode the visible text as `ABCD`.

Before the fix, an ad hoc probe decoded `ABCD` but produced styled bbox `[0,0,48,12]`, showing the invalid later range had cleared the earlier CID map before source-CID word spacing. After the fix, the styled bbox is `[0,0,72,12]`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - no syntax errors
- `php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-invalid-cidrange-source-width-currentbase.php`
  - no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  - `1 test files, 359 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthOrderCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapMultiRangeSparseSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapNotdefCharSourceWidthCurrentBaseTest.php`
  - `6 test files, 429 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-invalid-cidrange-source-width-currentbase.php`
  - emits `invalid_later_cidrange_ignored=true`, `visible_text_imported=true`, `false_decoded_word_gap_excluded=true`, `cmap_program_bytes_visible_text_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2442 -> 2443`
- `wordpressScenarios`: `2082 -> 2083`
- Focused assertion count for `PdfCMapSourceWidthFallbackCurrentBaseTest.php`: `349 -> 359`
- Mapped upstream denominator unchanged; this is additive native PHP coverage inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss fallback, high/large CID range expansion, sparse or multi-range codespace ranking, late valid CID range/source row ordering, notdef range/char semantics, bytewise codespace membership, ToUnicode bfrange array fallback, vertical `/W2`, indirect width operands, Type3 widths, xref repair, stream filters, metadata, annotations, forms, image/filter review, OCR, or model execution.

The bounded behavior is only invalid later Encoding CMap CID ranges above the valid CID space preserving earlier valid source-CID mappings before source-width fallback.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, Type0 Encoding CMap source segmentation, CIDFont width metrics, text-state spacing, styled-span extraction, and WordPress smoke path. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
