# Inline Image Tokenizer Same-Line Graphics Prefix Boundary

Slice: `markerpdf-inline-image-tokenizer-boundary-current-base-20260606T062542Z`
Base accepted HEAD: `ff6d9ac7ac50ba24390bdd95da205dfc798a98c3`

## Source Truth

- Upstream markerPDF text extraction stays in the PDF text path and does not run OCR/model workers for searchable PDFs: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`.
- Local scope is the native PHP inline-image tokenizer fallback for preview-only filters, not raster decoding, OCR, Surya/Texify/Torch, or model benchmark parity.

## Behavior

Preview-only inline image fallback closure now accepts valid same-line content prefixes after the real `EI` terminator before a later stray `EI` operator. The covered prefixes are:

- `q ... Q`
- numeric color state, such as `0 0 1 rg`
- XObject painting through `/Decorative Do`
- path clipping through `re W n`

Before the source edit, throwaway current-base probes preserved only the text before the inline image and after the later stray operator, while swallowing the visible same-line `q`, color, `Do`, and clipping-prefix text.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` => `1 test files, 462 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php` => `12 test files, 1933 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` smoke passed with `preview_only_same_line_graphics_prefix_stray_ei_text_preserved_after_safe_boundary=true`
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-tokenizer-boundary-currentbase.php` => no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`
- `git diff --check -- lanes/markerpdf` => passed with no output

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted line-separated text fallback, same-line raw text fallback, `BX` compatibility-section fallback, external `Q`/`EMC`/`EX` closure, native inline image decoding, DCT/JPX/CCITT filter boundaries, or unsupported-filter payload exclusion. The new boundary is the same-line valid content-prefix segment between preview-only inline image `EI` and visible text before a later stray `EI`.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP content tokenizer, inline image fallback scanner, PDF graphics/text operator validation, and WordPress smoke harness.
