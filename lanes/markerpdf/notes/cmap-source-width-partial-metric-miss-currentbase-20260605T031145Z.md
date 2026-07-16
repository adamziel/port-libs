# markerPDF CMap source-width partial metric-miss current base

Session: `port-dev-markerpdf-source-width-20260605T031145Z`
Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260605T031145Z`
Base accepted HEAD: `5fa3b785574733506c7d7bc664e972380aeaa321`

## Source Truth

- Upstream `sddai/markerPDF` at pinned manifest commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py` and the `pdftext.extraction.dictionary_output` dependency boundary before Marker builds page/block/line/span output.
- The native PHP no-GPU fallback owns CMap source-code segmentation and CIDFont width advances before WordPress paragraph grouping when pdftext, pypdfium2/PDFium, Python, OCR/model workers, and external PDF tools are unavailable.
- In malformed Type0 PDFs, a predefined `/Identity-H` source chunk can have valid direct CID width evidence while a later source chunk only has mapped ToUnicode source-byte width evidence. The width fallback must be chunk-local so one valid direct CID does not block fallback for later missing chunks.

## Behavior Added

`PdfTextExtractor::textOperandSourceKeysForFontWidths()` still prefers CID/Encoding CMap source chunks for font advance lookup. When those Identity-H chunks include a mix of direct CID width hits and misses, `toUnicodeSourceKeysForPartialCidMetricMiss()` now preserves direct CID chunks and splits only the missing chunks through mapped ToUnicode source keys that have `/W`, `/DW`, or CIDSet width evidence.

Visible text decoding is unchanged. The change is bounded to text advance segmentation, same-line positioned gap decisions, source-space counting, and native styled-span bboxes.

## Focused Fixture

`PdfCMapSourceWidthFallbackCurrentBaseTest.php` adds a Type0 `/Identity-H` fixture with:

- a ToUnicode CMap declaring one-byte source codes `<41>` through `<48>`;
- text operands `<41424344>` and `<45464748>`;
- descendant CIDFont `/W` widths for source bytes `65..72`, plus one direct combined CID width for `16706` (`0x4142`);
- a second `Tm` positioned so over-advancing the missing `0x4344` chunk incorrectly joins `ABCDEFGH`, while chunk-local fallback preserves `ABCD EFGH`.

## Evidence

Red-focused check before the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL falls back per source chunk when Identity-H has partial CID metric evidence on current base
Expected: array (0 => 'ABCD EFGH',)
Actual: array (0 => 'ABCDEFGH',)
1 test files, 100 assertions, 1 failures
```

Passing direct focused gate after the source fix:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 109 assertions, 0 failures
```

Adjacent CMap/font regression gate:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType0CidSetVerticalSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthCMapFallbackFlagsCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCMapUseCMapVerticalWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 9 selected test files (root lock skipped)
9 test files, 912 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php
```

The smoke emits `identity_partial_metric_miss_tounicode_chunks_applied=true`, `identity_partial_metric_miss_direct_cid_width_preserved=true`, `identity_partial_metric_miss_false_join_excluded=true`, `identity_partial_metric_miss_span_widths=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Status Delta

- `phpPass`: `1339 -> 1340`
- `wordpressScenarios`: `1286 -> 1287`
- Mapped upstream denominator stays unchanged; this is an additive current-base PHP behavior case inside the already mapped font/CMap source-width fallback cluster.
- Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, predefined Identity-H source widths, predefined UCS2-H fallback, explicit `/W` all-miss fallback, `/DW`-only all-miss fallback, `TJ` horizontal/vertical adjustment gaps, odd-hex right-padding, one-byte codespace padding fallback, Type0 Encoding CMap CID width priority, UseCMap inheritance, indirect `/W`/`DW`/`W2` operands, CIDSet grouping, Type3 CMap width grouping, quote-operator spacing, vertical `/W2`, styled-span width-advance geometry, xref repair, parser stream boundaries, metadata, annotations, forms, or image/filter review.

The bounded behavior is specifically partial Identity-H metric-miss fallback where direct CID width evidence for one source chunk must not block mapped ToUnicode source-key fallback for later missing chunks before WordPress paragraph rendering.

## Dependency Closure

No new support component is needed. This reuses the native PDF object scanner, stream decoder, ToUnicode CMap parser, predefined CID CMap source tokenizer, CIDFont width metrics, content-token text-positioning path, styled-span bbox path, and WordPress smoke renderer. Full upstream model/OCR runner parity remains intentionally out of scope under the current no-GPU markerPDF directive.
