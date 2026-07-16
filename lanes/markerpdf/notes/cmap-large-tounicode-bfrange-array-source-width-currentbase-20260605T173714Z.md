# markerPDF CMap Large ToUnicode Bfrange Array Source Width

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T173714Z`

Base accepted HEAD: `c6a8d542e199c5922210b1e1a777006ffcdcda14`

## Source Truth

Upstream `sddai/markerPDF` at manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through PDF parser/font/CMap machinery before Marker builds spans, lines, blocks, and Markdown. In this no-GPU markerPDF lane, the native PHP parser owns the searchable-PDF fallback boundary without running pdftext, pypdfium/PDFium, OCR, Surya, Texify, Torch, model workers, or external PDF tools.

PDF ToUnicode CMaps allow array-form `beginbfrange` rows, where each valid source code in the range maps to the corresponding target array entry. The existing scalar large-`bfrange` lazy path preserved mappings beyond the 4096-entry eager expansion cap, but array-form rows past that cap fell back to raw source-code text.

## Implementation

`PdfTextExtractor::parseToUnicodeRanges()` now records array-form `beginbfrange` target lists as lazy `unicodeRanges` metadata, alongside the existing scalar `target` metadata. `toUnicodeRangeTextForSourceKey()` resolves those lazy array ranges by source offset, including existing sparse code-space sequence handling, and decodes the selected target only when the source key is inside the range and the target entry exists.

The focused fixture uses:

- Type0 `/Encoding` CMap code space `<0000> <FFFF>`;
- `begincidrange <0000> <1007> 1000`;
- ToUnicode array `beginbfrange <0000> <1007> [...]` with 4104 targets;
- page text source codes `<1000>` through `<1007>`, just past the 4096 eager expansion cap;
- descendant `/W [5096 5099 1000 5100 5103 250]` metrics proving source-width grouping.

## Evidence

Red-first after adding the fixture and before the parser change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php
PASS uses lazy large ToUnicode bfrange rows past eager expansion cap before source-width fallback on current base
FAIL uses lazy large ToUnicode bfrange array rows past eager expansion cap before source-width fallback on current base
Expected: U+1041..U+1048 array target text
Actual: U+1000..U+1007 raw source-code fallback
1 test files, 11 assertions, 1 failures
```

Focused after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php
PASS uses lazy large ToUnicode bfrange rows past eager expansion cap before source-width fallback on current base
PASS uses lazy large ToUnicode bfrange array rows past eager expansion cap before source-width fallback on current base
1 test files, 20 assertions, 0 failures
```

Adjacent CMap/text regression check:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapLargeToUnicodeBfrangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfCMapLargeCidRangeSourceWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapBfrangeSurrogateWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
5 test files, 968 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-large-tounicode-bfrange-array-source-width-currentbase.php
```

The smoke emits `large_tounicode_bfrange_array_decoded=true`, `text_runs_preserved=true`, `large_cidrange_source_widths_applied=true`, `unmapped_source_fallback_excluded=true`, `raw_nul_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`, then renders one Gutenberg paragraph for the decoded U+1041..U+1048 text.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted scalar large ToUnicode `bfrange` lazy lookup, large CID range lookup, sparse code-space scalar `bfrange` offset handling, ToUnicode block-order precedence, CID CMap `cidchar` ordering, zero-padded source-width fallback, predefined CMap source-width fallback, repeated zero padding, explicit longer source-key precedence, malformed broad codespace recovery, notdef rows, horizontal/vertical `TJ` gaps, indirect width operands, Type3 widths, xref repair, stream filters, annotations, forms, metadata, images, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically lazy array-form ToUnicode `beginbfrange` lookup beyond the eager expansion cap before source-width grouping.

## Status Delta

- `phpPass`: `2115 -> 2116`
- `wordpressScenarios`: `1824 -> 1825`
- Mapped upstream denominator unchanged; this is additive current-base PHP behavior inside the already mapped CMap/font source-width fallback cluster.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, ToUnicode lazy range lookup, Type0 Encoding CMap parser, CIDFont width metrics, text-run/line/styled-span extraction, and WordPress smoke path. Full OCR/model/PDFium benchmark parity remains intentionally out of scope under the current no-GPU markerPDF directive.
