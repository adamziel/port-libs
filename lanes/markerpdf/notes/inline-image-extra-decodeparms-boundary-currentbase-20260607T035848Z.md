# Inline Image Extra DecodeParms Boundary Current Base

## Source Truth

- Upstream `sddai/markerPDF` routes searchable PDF text through page/content extraction before OCR/model fallbacks, and routes image rendering through parser/PDFium/PIL image paths. Under the current no-GPU PHP scope, inline `BI ... ID ... EI` payload bytes remain raster data and must not become WordPress-visible text.
- PDF stream `/Filter` and `/DecodeParms` arrays are positional. A non-null DecodeParms dictionary that is not aligned with a concrete filter slot is ambiguous and unsafe for native decoding. The native PHP stream-filter stack already fails closed for extra/unapplied DecodeParms; this slice applies the same boundary to native inline image filters.

## Behavior

- `PdfTextExtractor` now treats native inline image filters as unsupported for tokenizer purposes when `/DecodeParms` contains extra non-null slots that do not correspond to a concrete filter. This keeps delimiter-looking inline payload bytes closed until the real `EI` and preserves following page text.
- `PdfImageRenderer` now reports those native inline filters as review-only and blocks RGB preview decoding with `unaligned_native_decodeparms_fail_closed` metadata:
  - `decode_parms_alignment`: `unapplied_filter_slot`
  - `invalid_decode_parms_fields`: `["decode_parms_alignment"]`
  - `unapplied_decode_parms_slots`: the extra non-null slots.
- Clean, aligned native Flate DecodeParms still decode normally before preview rows.

## Red-First Evidence

Before the source change, after adding the focused regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 889 assertions, 1 failures`

Failure: `fails closed on extra non-null inline image DecodeParms slots before text extraction and preview` only returned `Before Extra Inline DecodeParms`, because the inline payload consumed the following text boundary.

## Verification

Focused after the fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 914 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-extra-decodeparms-currentbase.php`

Result: exits `0` with `extra_decodeparms_inline_filter_preview_failed_closed=true`, `extra_decodeparms_inline_filter_unsupported=["FlateDecode"]`, `extra_decodeparms_native_raster_decode=false`, `extra_decodeparms_review="unaligned_native_decodeparms_fail_closed"`, `extra_decodeparms_unapplied_slots=[1]`, `inline_payload_excluded_from_text=true`, and no Python/models/PDFium/PIL/external PDF tools.

Adjacent focused family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`

Result: `4 test files, 1886 assertions, 0 failures`

Required syntax and diff checks:

- `php -l lanes/markerpdf/src/PdfImageRenderer.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-extra-decodeparms-currentbase.php` => no syntax errors.
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`.
- `git diff --check -- lanes/markerpdf` => no output.

## Non-Overlap

This does not repeat accepted inline image sample-floor tokenization, ASCIIHex/ASCII85/LZW/RunLength EOD boundaries, native filter prefix handling, null filter DecodeParms alignment, invalid native DecodeParms value fail-closed handling, duplicate DecodeParms declarations, malformed filter operands, indirect geometry/Decode operands, duplicate `/Decode` operands, DCT/CCITT/JPX/JBIG2 preview-only boundaries, Image XObject Decode metadata, stream-filter extra DecodeParms coverage, OCR/model execution, or external raster rendering.

The bounded behavior is specifically native inline image filters with extra non-null `/DecodeParms` slots that have no concrete filter owner.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF text tokenizer, inline image dictionary parser, stream filter DecodeParms alignment helpers, image renderer review metadata, and WordPress smoke renderer. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL rasterization, external PDF tools, and exact upstream model benchmark parity remain intentionally outside the current no-GPU markerPDF scope.
