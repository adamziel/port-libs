# markerPDF Inline Image Abbreviation DecodeParms

Slice: `markerpdf-inline-image-abbrev-decodeparams-current-base-20260602T065630Z`

## Source Truth

Upstream markerPDF at `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` delegates native PDF text extraction through `marker/pdf/extract_text.py` into pdftext/pypdfium parsing. At that boundary, inline image data is an image payload, not visible text.

PDF parser source truth for inline images allows abbreviated keys and values such as `/F`, `/DP`, `/W`, `/H`, `/BPC`, `/G`, and `/Fl`; `DecodeParms` supplies predictor parameters for Flate/LZW decoded image bytes. A native parser must not stop at delimiter-looking `EI` bytes inside compressed image data and then treat the rest of the image payload as content-stream operators.

## Implementation

`PdfTextExtractor::skipInlineImage()` now parses the inline image dictionary up to `ID`, expands the standard inline image key/value abbreviations, and verifies candidate `EI` markers for decodable filters by running the declared filter chain. When image dimensions and color depth are known, the candidate must also decode to the expected image sample byte count.

This lets `/F /Fl /DP << /Predictor 12 /Columns ... >>` inline image data skip over fake ` EI ` byte sequences inside compressed payloads while still preserving the old fallback for unfiltered or unsupported image-only filters.

## WordPress Path

`examples/wordpress-pdf-inline-image-abbrev-decodeparms-import.php` models a PDF import where a compressed inline image contains the literal bytes ` EI BT ... (Inline DP Image Noise) Tj ET` inside its Flate payload. The smoke emits only `Before DP Inline Image` and `After DP Inline Image` as Gutenberg paragraphs.

## Dependency Closure

No new support component is needed. The slice reuses the native PDF content tokenizer, stream filter dispatcher, DecodeParms predictor decoder, and page text extraction path. Full upstream Python/model/benchmark parity remains dependency-gated on pdftext, pypdfium2, Surya/Torch/model downloads, tabled-pdf, Texify, and upstream benchmark tooling.

## Verification

- Red-first probe before the fix: the same fixture leaked `Inline DP Image Noise` into `extractTextLines()`.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php` passed.
- `php -l lanes/markerpdf/tests/PdfTextExtractorTest.php` passed.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-abbrev-decodeparms-import.php` passed.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php` passed: 1 file, 408 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-abbrev-decodeparms-import.php` emitted `fake_ei_inside_compressed_payload=true`, `visible_text_imported=true`, and `excluded_inline_image_text=true`.

## Non-Overlap

This does not repeat accepted inline image raw `BI / ID / EI` exclusion, indirect numeric DecodeParms predictor stream filters, filter-chain DecodeParms arrays, image XObject exclusions, DCT/CCITT/JPX/JBIG2 boundaries, or stream-filter error-boundary fail-closed behavior. The new behavior is specifically inline image abbreviation expansion plus `DecodeParms`-aware validation of candidate `EI` markers inside compressed image data.
