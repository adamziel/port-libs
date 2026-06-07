# Inline Image Duplicate DecodeParms Boundary Current Base

## Source Truth

Upstream markerPDF keeps inline image bytes out of text extraction through `marker.pdf.extract_text.get_text_blocks`, while image rendering reaches `marker.pdf.images.render_image` through PDFium/PIL for raster formats. Under the current no-GPU/no-model markerPDF scope, this native PHP slice preserves that boundary: ambiguous inline image filter metadata must fail closed before text extraction and before PHP native preview decoding.

## Behavior

- Canonical inline image dictionaries already expand `/DP` to `/DecodeParms`; this slice treats a subsequent full `/DecodeParms` declaration as a duplicate top-level decode-parameter declaration.
- `PdfImageRenderer` now marks native filters such as `FlateDecode` review-only when duplicate `/DecodeParms` declarations are present, records `decode_parms_review=duplicate_native_decodeparms_declaration_fail_closed`, and blocks native preview decoding.
- `PdfTextExtractor` now keeps a duplicate-aware first DecodeParms operand only for inline-image ownership fallback. That lets the tokenizer reject fake `EI` bytes inside image payloads and still close at the real inline image terminator without using the ambiguous operand for native decode.
- A clean single `/DP` Flate inline image still decodes to RGB preview rows.

## Red-First Evidence

Before the source fix, a renderer probe with `/F /Fl /DP << /Predictor 1 >> /DecodeParms << /Predictor 12 /Columns 0 >>` returned `native_raster_decode=true` and no unsupported filters for the inline Flate image.

The first focused run after the renderer-only fix also exposed the tokenizer boundary: the duplicate DecodeParms payload stayed out of text, but text after the real `EI` was swallowed because duplicate DecodeParms made the inline preview fallback lose its operand list.

## Verification

- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors.
- `php -l lanes/markerpdf/src/PdfImageRenderer.php` => no syntax errors.
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-duplicate-decodeparms-currentbase.php` => no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php` => `1 test files, 873 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-duplicate-decodeparms-currentbase.php` => exits 0 and emits metadata with `decode_parms_review=duplicate_native_decodeparms_declaration_fail_closed`, `duplicate_preview_failed_closed=true`, `inline_payload_excluded_from_text=true`, and no Python/model/PDFium/PIL/external-tool execution.

## Non-Overlap

This does not repeat accepted inline ASCII85/ASCIIHex/Flate predictor/LZW/RunLength EOD boundaries, null-filter DecodeParms slot alignment, invalid native DecodeParms parameter handling, indirect `/Decode` operand resolution, duplicate inline `/Decode` declarations, DCT duplicate DecodeParms declarations, CCITT duplicate DecodeParms parameters, stream XObject duplicate `/DecodeParms` keys, or OCR/model/PDFium raster execution. The bounded behavior is only duplicate inline native `/DecodeParms` declarations formed by abbreviation expansion plus a full key.

## Dependency Closure

No new support component is needed. The slice reuses existing native tokenizer, dictionary, zlib/Flate, and RGB-preview helpers. Full raster parity for preview-only image formats remains intentionally out of scope without PDFium/PIL/model execution.
