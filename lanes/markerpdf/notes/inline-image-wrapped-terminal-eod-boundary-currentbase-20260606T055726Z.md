# Inline Image Wrapped Terminal EOD Boundary Current Base - 2026-06-06

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T055726Z`

Accepted base: `cf7ad8dedfdead64d21e5ec92010b21088cacf79`

## Source Truth

Native no-GPU markerPDF work only. This slice stays in searchable-PDF parser behavior and inline image filter metadata. Upstream PDF inline images are terminated by `EI` in the content stream, but native filtered image bytes may contain delimiter-looking text after a terminal filter EOD marker. For wrapped stacks such as `[/AHx /LZW]` and `[/AHx /RL]`, the fake `EI` is hidden until the outer filter is decoded, so tokenizer boundary recovery must inspect the terminal filter input before deciding whether a candidate `EI` belongs to image data or the content stream.

## Red-First Probe

Before the source change, focused probes with wrapped terminal LZW and RunLength payloads emitted only the before-text:

- `Before Wrapped Terminal LZW Inline`
- `Before Wrapped Terminal RunLength Inline`

The after-text was lost because the tokenizer accepted the delimiter-looking decoded surplus boundary before the real raw `EI`.

## Implementation

- `PdfTextExtractor` now checks wrapped native terminal EOD filters after decoding the prefix filters and before falling through to malformed inline image recovery.
- The check is conservative: it requires a positive sample floor, at least two non-null filters, a bounded terminal filter EOD offset, non-whitespace decoded surplus containing delimiter-looking `EI`, and enough decoded terminal bytes to satisfy the declared sample floor.
- Preview behavior remains fail-closed for surplus payloads; clean LZW and RunLength preview payloads still decode through the expected filter stacks.

## Focused Evidence

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => `1 test files, 705 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php` => `3 test files, 513 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` => smoke metadata emitted `wrapped_terminal_lzw_payload_excluded_until_real_ei=true`, `wrapped_terminal_lzw_clean_preview_decoded=true`, `wrapped_terminal_runlength_payload_excluded_until_real_ei=true`, and `wrapped_terminal_runlength_clean_preview_decoded=true`.
- `php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "valid json\n";'` => `valid json`.
- `git diff --check -- lanes/markerpdf` => no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the prior ASCII85/ASCIIHex/Flate/JPEG/JPX/CCITT inline boundary slices. Existing wrapped terminal Flate coverage remains unchanged. This patch owns only wrapped native terminal explicit-EOD filters where the fake delimiter appears after decoding an outer native wrapper.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP stream filter helpers, decode-parameter resolution, and inline image sample-floor metadata. GPU/OCR/model execution remains intentionally out of scope under the current markerPDF no-GPU directive.

## Next Task

Continue native no-GPU markerPDF work on a distinct parser/import-fidelity gap such as fonts, CMaps, xref repair, metadata, forms, annotations, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
