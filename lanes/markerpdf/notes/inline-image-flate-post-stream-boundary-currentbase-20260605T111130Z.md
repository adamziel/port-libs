# Inline Image Flate Post-Stream Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T111130Z`
Base accepted HEAD: `c13b20681f4805fae1edeffd61b3ffb4b45a217c`

## Upstream Boundary

Maps the native no-GPU PDF parser boundary for content-stream inline images:
`BI` dictionaries, `ID` payload bytes, native `FlateDecode` decoding, and the
real `EI` terminator must keep raster payload bytes out of WordPress-visible
text. This mirrors markerPDF's searchable-PDF text extraction path without
running OCR/model/image raster backends.

## Behavior

- `PdfTextExtractor` now treats Flate inline-image streams as bounded zlib,
  raw-deflate, or gzip members when validating candidate `EI` positions.
- If a complete Flate member is followed by text-like surplus containing a fake
  `EI`, the tokenizer waits for the later real inline-image terminator.
- `PdfImageRenderer` inline preview validation now fails closed when Flate
  payloads contain non-whitespace bytes after the consumed compressed member,
  while still decoding valid Flate inline-image samples.

## Evidence

Red-first focused run before implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 314 assertions, 1 failures`; failing case:
`keeps Flate post-stream inline image surplus closed until the real EI terminator`
leaked `Flate Post Stream Inline Noise` into extracted text.

After implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 329 assertions, 0 failures`.

Smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-flate-post-stream-boundary-currentbase.php`

Result: emitted clean WordPress paragraph HTML for `Before Flate Inline Image
Import` and `After Flate Inline Image Import`; metadata reported
`payload_excluded_from_visible_text=true`,
`post_stream_preview_rejected=true`, and no Python/models/external PDF tools.

## Dependency Closure

No new support component is needed. The patch reuses PHP's local zlib
incremental inflate APIs (`inflate_init`, `inflate_add`,
`inflate_get_read_len`) only to identify consumed Flate member length inside
native inline-image parser boundaries.

## Non-Overlap

This does not repeat the accepted DCTDecode XObject native-prefix boundary,
inline DCT/JPEG EOI tests, JPX/JBIG2/CCITT preview-only tests, malformed filter
operand fail-closed tests, or LZW/RunLength/ASCIIHex EOD surplus coverage. It
targets Flate inline-image post-stream surplus before the real `EI` terminator.
