# Inline Image Decode Boundary Current Base - 2026-06-06T02:47:35Z

## Slice

- Lane: `markerpdf`
- Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T024735Z`
- Accepted base: `835c273c51f77a1896fa4f56496ca13e5f4b02f3`
- Scope: native no-GPU PDF content-stream inline image tokenizer/filter boundary behavior.

## Source Truth

Upstream markerPDF routes searchable PDF text separately from image rendering. Inline images use the PDF `BI ... ID ... EI` content-stream form, so bytes owned by a completed inline image filter must not become visible WordPress paragraph text. When `/ColorSpace` or `/BitsPerComponent` is absent, the native tokenizer cannot derive a decoded sample floor, but standard native filters still expose their own end boundaries.

## Red-First Evidence

Before source edits, inline PHP probes on the accepted base showed the text extractor returned only the `Before` line for these malformed no-sample-floor inline images:

- `/F /Fl` with `gzcompress("K") . "ZZ EI BT ... rawtail"` followed by a real inline-image `EI`.
- `/F [/AHx /Fl]` with `ASCIIHex(gzcompress("K") . "ZZ EI BT ... rawtail") >` followed by a real inline-image `EI`.
- Similar direct ASCIIHex, LZW, and RunLength probes also swallowed the following visible text.

The renderer already failed closed for output preview, so the gap was tokenizer ownership, not RGB preview leniency.

## Implementation

- `PdfTextExtractor` now recognizes a completed single native inline image filter member even when no decoded sample floor is available.
- The new path requires post-filter surplus containing a later delimiter-looking `EI`, decodes only the bounded native filter bytes, and requires non-empty decoded data.
- The existing wrapped-terminal-Flate surplus helper now accepts a nullable sample floor and uses the decoded terminal Flate member only for tokenizer ownership.
- Preview helpers still reject no-sample-floor surplus and incomplete sample data before RGB output.

## Tests And Smoke

Focused verification:

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` => no syntax errors
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => `1 test files, 636 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php` => `6 test files, 1094 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php` metadata check confirms `visible_text_imported=true`, `no_floor_native_filter_payloads_excluded_until_real_ei=true`, `no_floor_native_filter_preview_remains_fail_closed=true`, `wrapped_no_floor_terminal_flate_payload_excluded_until_real_ei=true`, `wrapped_no_floor_terminal_flate_preview_remains_fail_closed=true`, `excluded_inline_image_text=true`, and both Python/model/external-tool execution flags are false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted ASCII85/ASCIIHex/RunLength/LZW post-EOD sample-floor handling, direct null filters, Flate predictor short-row ownership, short decoded Flate surplus, stacked native filter first-EOD surplus, wrapped terminal Flate with declared sample floor, Identity Crypt + Flate/JPX boundaries, raw JPX post-EOC surplus, Flate-wrapped JPX no-floor preview framing, malformed filter operands, or inline image preview-only tokenizer fallbacks. The bounded behavior is specifically native inline image filter ownership when no decoded sample floor can be derived.

## Dependency Closure

No new support component is needed. This reuses native PHP stream-filter boundary helpers, zlib/LZW/RunLength/ASCII filter decoding, the existing inline image tokenizer, focused PHP tests, and the existing WordPress smoke. Live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive; no Python, GPU/model worker, pypdfium/PIL, external PDF tool, or online service was run.

## Next Task

Continue non-overlapping native no-GPU markerPDF work around fonts/CMaps, stream filters, xref repair, metadata, annotations/forms, page geometry, image/filter metadata, and supplied-boundary table/equation handoffs.
