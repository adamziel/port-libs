# markerPDF CMap orphan bfchar source-width fallback

Session: `port-dev-markerpdf-source-width-20260607T002047Z`

Micro-slice: `markerpdf-cmap-source-width-fallback-current-base-20260607T002047Z`

Base accepted HEAD: `fcfc1289838c2e7d72110cd0e9fb80086fd87cb6`

## Source Truth

The lane manifest pins upstream `sddai/markerPDF` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`. Upstream markerPDF delegates searchable-PDF text extraction through PDF parser font/CMap decoding before page text and Markdown/WordPress output. In this no-GPU PHP lane, malformed ToUnicode CMap rows must not shift valid later source-to-Unicode rows before Type0 CIDFont source widths and styled span grouping are computed.

## Behavior

`PdfTextExtractor::parseToUnicodeCMap()` now uses a row-aware fallback for `beginbfchar` blocks when a malformed row contains an orphan singleton source operand. The normal token-pair parser remains the default. The row-aware result is used only when malformed row shape is detected and the recovered row pairs still satisfy the declared mapping count, so valid compact rows and `beginbfrange` parsing are left unchanged.

The focused fixture uses:

- a Type0 font with `/Encoding /Identity-H`;
- a ToUnicode CMap with `3 beginbfchar`, an orphan singleton `<0009>`, then valid rows `<0001> <0041>`, `<0002> <0042>`, and `<0003> <0043>`;
- descendant CIDFont widths `/W [1 2 1000 3 3 250]`;
- content split into `<00010002>` and `<0003>` at adjacent text positions.

Before the fix, the orphan singleton shifted the token pairs and no visible text line was recovered. After the fix, the extractor imports `ABC`, preserves text runs `AB` and `C`, and keeps source-width bboxes `[0,0,24,12]` and `[24,0,27,12]`.

## Verification

- Red-first before source edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapOrphanBfcharSourceWidthCurrentBaseTest.php`
  - `1 test files, 1 assertions, 1 failures`
  - failure: expected `['ABC']`, actual `[]`
- Focused after source edit: `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapOrphanBfcharSourceWidthCurrentBaseTest.php`
  - `1 test files, 11 assertions, 0 failures`
- Adjacent CMap source-width/width family: `php tools/run-tests.php $(find lanes/markerpdf/tests -maxdepth 1 -type f \( -name 'PdfCMap*SourceWidth*Test.php' -o -name 'PdfFontCid*Width*Test.php' -o -name 'PdfFontCMap*Width*Test.php' -o -name 'PdfFontType0*Width*Test.php' \) | sort)`
  - `25 test files, 634 assertions, 0 failures`
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-cmap-orphan-bfchar-source-width-currentbase.php`
  - emits `orphan_singleton_bfchar_ignored=true`, `later_bfchar_rows_recovered=true`, `source_width_spans_applied=true`, `control_text_excluded=true`, `cmap_program_bytes_excluded=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

An exploratory broader command that included `PdfParserMalformedCMapFilterBoundaryCurrentBaseTest.php` still reports the stale-generation CMap filter-owner fixture importing its compressed CMap leak. That failure is outside this slice: the leaking CMap row is a normal two-token `bfchar` row, so the new orphan-row fallback is not selected for it.

## Status Delta

- `phpPass`: `2719 -> 2720`
- `wordpressScenarios`: `2291 -> 2292`
- Added 1 focused PHP PASS case with 11 assertions.
- Added 1 WordPress smoke scenario.
- Mapped upstream denominator unchanged; this is additive native PHP coverage inside the already mapped CMap/font source-width fallback cluster.

## Non-Overlap

This does not repeat accepted zero-padded source-width fallback, Identity-H/UCS2 predefined source widths, metric-miss fallback, partial metric-miss fallback, high/large CID range expansion, lazy large ToUnicode bfrange lookup, sparse or multi-range codespace ranking, delayed codespace starts, late ToUnicode block precedence, late CID row precedence, malformed short bfrange arrays, notdef range/char semantics, bytewise codespace membership, array-wrapped CID decoys, invalid/overflow CID ranges, vertical `/W2`, indirect width operands, Type3 widths, xref repair, stream filters, metadata, annotations, forms, image/filter review, OCR, or model execution.

The bounded behavior is specifically orphan singleton source operands inside ToUnicode `beginbfchar` blocks before valid later `bfchar` rows and source-width grouping.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream decoder, CMap parser, Type0 font map construction, CIDFont width parser, text line/run/styled-span extraction, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch, raster PDFium/PIL rendering, JavaScript/action execution, and exact upstream GPU/model benchmark parity remain intentionally out of scope.
