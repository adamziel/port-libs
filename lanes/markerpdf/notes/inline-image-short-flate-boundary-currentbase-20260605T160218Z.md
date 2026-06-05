# Inline image short Flate boundary current-base slice

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T160218Z`
Base accepted HEAD: `1ec299d70fc84b468f2f246042c3fd21c99bd4eb`

## Source-truth behavior

This stays inside the native no-GPU markerPDF scope. PDF inline-image bytes can contain text-like `EI` sequences before the actual inline-image terminator. When the inline image uses Flate, the completed zlib/deflate member is a bounded native filter signal even though the decoded raster payload may be shorter than the declared `/W` x `/H` sample floor. In that malformed-short case, text extraction should keep post-stream false `EI` bytes image-owned until the later real terminator, while image preview/raster metadata remains fail-closed for incomplete samples.

The pre-fix probe on this base used `/F /Fl`, `/W 3`, `/H 1`, two decoded bytes, post-stream surplus containing `ZZ EI ...`, and then a real `EI`. It returned only `Before Flate Short Sample`; the following searchable text was swallowed. This patch preserves the following paragraph and keeps preview rejection explicit.

## Patch

- `PdfTextExtractor::inlineFlateCandidateReachesSampleFloorBeforePostStreamSurplus()` now treats a completed non-empty Flate member before text-like post-stream surplus as an image-owned boundary even when decoded samples are short.
- `PdfInlineImageDecodeBoundaryCurrentBaseTest.php` adds the focused red case and asserts text extraction/import survives while `PdfImageRenderer` rejects both clean short and surplus short preview rows.
- `wordpress-pdf-inline-image-decode-boundary-currentbase.php` adds a WordPress smoke row and metadata flags for the same short decoded Flate post-stream surplus boundary.
- `lane-status.json` records `+1` focused PHP PASS case and `+1` WordPress scenario.

## Non-overlap

This does not repeat the accepted oversized Flate sample-floor surplus, predictor short-row Flate boundary, ASCII85/AHx/LZW/RunLength EOD surplus, JPX/JBIG2/DCT/CCITT preview-only fallback, Identity Crypt Flate prefix, image XObject tiling-pattern review, OCR/model, or external PDF-tool surfaces. The new cluster is specifically completed Flate members with decoded byte count below the declared inline image sample floor.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - `1 test files, 443 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php`
  - `1 test files, 299 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php > /tmp/markerpdf-inline-image-decode-boundary-currentbase.html` plus metadata check
  - `visible_text_imported=true`
  - `short_flate_post_stream_payload_excluded_until_real_ei=true`
  - `short_flate_preview_rejected=true`
  - `short_flate_surplus_preview_rejected=true`
  - `excluded_inline_image_text=true`
  - `paragraph_count=44`
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - no syntax errors
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  - no syntax errors
- `php -r '$data = json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true); if (!is_array($data)) { fwrite(STDERR, json_last_error_msg() . "\n"); exit(1); } echo "lane-status json ok\n";'`
  - `lane-status json ok`
- `git diff --check -- lanes/markerpdf`
  - no output

Root harness: not run - isolated micro-slice.

## Dependency closure

No new support component is needed. This reuses the existing native PHP Flate stream decoding and inline-image tokenizer/converter paths. No Python, OCR, Surya/Texify/Torch, live model workers, external PDF tools, or online services were run.
