# Inline Image Decode Post-EOD Surplus Boundary

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T100349Z`
Base: `9f1f2346a7dba9e945aa136b3a22616c0fc812cc`
Date: 2026-06-05 UTC

## Source Truth

Upstream/PDF native behavior for inline image filters requires explicit in-band
EOD markers for `ASCIIHexDecode`, `ASCII85Decode`, and `RunLengthDecode`.
Bytes after those EOD markers are not image samples. In the no-GPU markerPDF
scope, the WordPress media review path should therefore fail closed when
content-stream parser bytes appear after the filter EOD marker before `EI`
instead of treating the decoded prefix as trusted native raster data.

Red-first probes on the accepted base showed these payloads were accepted as
decoded previews:

- `A85`: `z~> EI BT ...`
- `AHx`: `41424344> EI BT ...`
- `RL`: `chr(3) . "ABCD" . chr(128) . " EI BT ..."`

## Implementation

- `PdfImageRenderer` now requires only whitespace after the explicit filter
  EOD marker before native inline image preview decoding accepts decoded sample
  bytes for ASCIIHex, ASCII85, and RunLength filters.
- `PdfTextExtractor` keeps ordinary stream repair compatible with the existing
  marker-present contract, but inline image candidate/prefix decoding now asks
  for the stricter bounded EOD marker check.
- RunLength bounded-EOD detection parses run controls so a literal sample byte
  `0x80` before the real EOD is still valid image data.
- The WordPress inline image decode boundary smoke now reports A85/AHx/RL
  post-EOD surplus preview rejection flags.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - Result: `1 test files, 301 assertions, 0 failures`
  - Delta: direct focused file increased from 289 to 301 assertions and adds 1
    focused PASS case.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  - Result: emits `inline_filter_post_eod_surplus_preview_rejected=true` with
    `ascii85_post_eod_surplus_preview_rejected`,
    `asciihex_post_eod_surplus_preview_rejected`, and
    `runlength_post_eod_surplus_preview_rejected` all true.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - Result: no syntax errors.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php`
  - Result: no syntax errors.
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - Result: no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  - Result: no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`
  - Result: `lane-status json ok`.
- `git diff --check -- lanes/markerpdf`
  - Result: no whitespace errors.

Additional broad family check:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php`
  - Result: `14 test files, 1959 assertions, 2 failures`
  - The failures are the existing `PdfTextExtractorTest.php` unsupported-filter
    guards: `Unsupported stale Length leak` and `Stacked Unknown Leak`. They
    are outside this inline image post-EOD surplus slice and should be handled
    as a separate stream-filter fail-closed follow-up.

## Dependency Closure

No new support component is needed. This reuses the existing native PHP stream
filter decoders and inline image preview/tokenizer infrastructure. No GPU,
OCR/model workers, external PDF tools, or live services were run.

## Non-Overlap

This does not repeat prior inline-image sample-floor, ASCIIHex EOD, tokenizer,
named colorspace, DCT/JPX, mask, or runtime-preflight slices. The bounded
change is limited to post-EOD surplus bytes for native ASCIIHex/ASCII85/
RunLength inline image preview/sample-boundary decisions.
