# Inline Image Literal Palette Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260608T081714Z`
Base: `9318ce97f670bb0c379833b2a4213a7bf03ac886`

## Source Truth

Upstream markerPDF routes PDF image rendering through `marker.pdf.images.render_image_rgb`, where Indexed color-space lookup strings are image palette bytes. Inline image abbreviations apply to PDF name tokens in the inline image dictionary, not to slash-like byte sequences inside PDF literal strings, hex strings, comments, or dictionaries.

## Implementation

- `PdfImageRenderer` now canonicalizes inline image array values with a token-aware scanner, expanding real name tokens such as `/I`, `/RGB`, `/AHx`, and `/Fl` while preserving literal palette bytes such as `(/G/RGB)`.
- `PdfTextExtractor` uses the same token-aware array canonicalization before inline-image sample-floor matching, keeping text extraction and renderer review metadata consistent.
- Added a focused Indexed inline-image fixture where the literal palette bytes are `2F472F524742` (`/G/RGB`) and the selected palette entry previews as RGB `[82, 71, 66]`.
- Added a WordPress smoke that proves the inline image payload stays out of visible text and no Python, OCR/model, pypdfium/PIL, or external PDF tool path runs.

## Evidence

- Red-first probe before the patch failed with `Indexed color-space lookup length does not match the declared high value and base components` after the literal palette was expanded as abbreviation names.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageLiteralPaletteBoundaryCurrentBaseTest.php` => `1 test files, 26 assertions, 0 failures`.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImage*CurrentBaseTest.php` => `10 test files, 1952 assertions, 0 failures`.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-indexed-literal-palette-currentbase.php` exits 0 and reports `literal_palette_lookup_hex=2F472F524742`, `palette_rgb=[82,71,66]`, and `excluded_inline_payload_text=true`.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF tokenizer, inline image dictionary parser, stream decoder, and image preview metadata pipeline. GPU/model OCR, pypdfium/PIL raster rendering, and external PDF tools remain intentionally out of scope under the current markerPDF no-GPU lane rules.

## Non-Overlap

This does not repeat accepted inline image filter-array/null-entry, ImageMask, DCT/JBIG2/JPX/CCITT, DecodeParms, duplicate Decode, malformed tail operand, tokenizer, Image XObject duplicate numeric operand, or OCR/model work. Root harness not run - isolated micro-slice.
