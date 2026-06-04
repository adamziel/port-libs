# CMap TJ Source-Width Gap Current Base

Slice: `markerpdf-cmap-source-width-fallback-current-base-20260604T232227Z`

Accepted base: `dfccfd252d4ec7968da59da8d0cbc92468a86823`

## Behavior

This patch keeps the native searchable-PDF path inside `PdfTextExtractor`. Horizontal `TJ` arrays that have CMap/source-boundary data now use the same CID/source-width advances already used for positioned text boundaries. When a numeric `TJ` adjustment creates a forward visual gap at the existing positioned-text threshold, the decoded line/plain/styled text receives one word space.

The scope is intentionally narrow:

- simple-font `TJ` arrays without source-boundary data keep the old concatenation behavior;
- vertical writing mode keeps the existing vertical CMap path;
- styled span width still comes from the original source operand, so the new decoded word space does not inflate the bbox.

## Source Truth

PDF `TJ` text arrays interleave strings with numeric positioning adjustments. The port already applies those adjustments to text-end geometry through `advanceTextEndXForOperand()`. The missing behavior was text decoding: a single source-width-backed `TJ` array such as `[<41424344> -1000 <45464748>] TJ` advanced by 72pt but decoded as `ABCDEFGH`, joining a visible 12pt gap before WordPress paragraph rendering.

Red-first probe before the patch:

- `extractTextLines()` returned `["ABCDEFGH"]`.
- styled span text was `ABCDEFGH`.
- styled span bbox was `[0.0, 0.0, 72.0, 12.0]`.

After the patch:

- `extractTextLines()` returns `["ABCD EFGH"]`.
- styled span text is `ABCD EFGH`.
- styled span bbox remains `[0.0, 0.0, 72.0, 12.0]`.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  `No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php`
- `php -l lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  `No syntax errors detected in lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
- `php -l lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php`
  `No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfCMapSourceWidthFallbackCurrentBaseTest.php`
  `1 test files, 47 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  `1 test files, 628 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfFontCidEncodingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidWidthResourceSpacingCurrentBaseTest.php lanes/markerpdf/tests/PdfFontCidType3ToUnicodeSpacingWidthCurrentBaseTest.php lanes/markerpdf/tests/PdfFontWidthAdvanceBoundaryCurrentBaseTest.php`
  `4 test files, 74 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-cmap-source-width-fallback-import.php`
  emits `tj_adjustment_source_width_gap_applied=true`, `tj_adjustment_false_join_excluded=true`, and `tj_adjustment_span_bbox_preserved=true`; no Python, model, OCR, or external PDF tool execution.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the prior zero-padded source-width, default-width, predefined Identity-H, or Identity-H metric-miss CMap fallback slices. Those covered gaps between separate text-showing operations. This slice covers an internal `TJ` numeric adjustment inside one text-showing operation.

It also does not repeat the earlier `Tw`/quote-operator or cross-`Tm` positioned text gap work. The new branch is gated on source-boundary CMap data and leaves the existing simple-font `TJ` comment/positioning tests unchanged.

## Dependency Closure

No new support component is needed. The patch reuses existing native PHP helpers for ToUnicode source boundaries, CID widths, source-space counting, and PDF content-stream `TJ` parsing. GPU/model OCR, Surya, Texify, Torch, and external PDF tools remain intentionally out of scope.
