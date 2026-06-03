# markerPDF Inline Image Decode Boundary Current Base

Session: `port-dev-markerpdf-inline-image-decode-20260603T093009Z`
Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260603T093009Z`
Base accepted HEAD: `ccdbc8f5f239ec3e14bb71edbef4e8cc79cd8677`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable text through `marker/pdf/extract_text.py` using pdftext/PDFium text extraction, while image rendering stays in `marker/pdf/images.py` and `marker/images/extract.py`. Inline image bytes are image payloads, not text spans.

Native PHP source truth for this slice is the PDF content-stream `BI ... ID ... EI` boundary. For encoded inline image payloads, delimiter-looking `EI` bytes inside native-decodable filters must not end the image before the encoded filter boundary is complete.

## Implementation

`PdfTextExtractor::inlineImageCandidateMatchesDictionary()` now validates inline-image filter candidates with explicit filter end-marker requirements. This keeps ordinary PDF streams permissive, but prevents inline `/ASCII85Decode` data from accepting an early `EI` candidate before the `~>` terminator.

The focused fixture also preserves the existing `/FlateDecode` + `/DecodeParms` predictor boundary: a compressed inline image payload containing literal ` EI ` bytes is decoded through the predictor before the end marker is accepted, so fake text operators inside the image payload stay out of WordPress paragraphs.

## Red-First Evidence

Before the change, a current-base probe for:

`BI /F /A85 ID 87cURDc^jtCh* EI BT ... (ASCII85 inline image leak) ... ~> EI`

returned visible text lines:

`Before A85`, `ASCII85 inline leak`, `After A85`

After the change, the focused test returns only:

`Before A85 Inline Image`, `After A85 Inline Image`

## Verification

Focused slice:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: `1 test files, 17 assertions, 0 failures`

Inline parser/filter family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php`

Result: `7 test files, 174 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`

Result: emitted four paragraphs with `fake_ei_inside_compressed_payload=true`, `fake_ei_inside_ascii85_payload=true`, `requires_ascii85_end_marker_before_ei=true`, `visible_text_imported=true`, and `excluded_inline_image_text=true`.

Syntax:

`php -l lanes/markerpdf/src/PdfTextExtractor.php`

`php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`

Result: both passed.

## Non-Overlap

This does not repeat accepted inline DCT/JPEG EOI validation, inline JPX EOC validation and soft-mask metadata, inline ImageMask preview rows, inline Indexed palette/alpha preview rows, inline filter-array abbreviation/null-entry metadata, object-stream inline image filter repair, page content stream ASCII85-to-Flate endstream boundary recovery, or generic image XObject payload exclusion.

The new behavior is specifically inline image decode-boundary validation for native filter candidates: ASCII85 must reach `~>` before delimiter-looking `EI` can close the image, and Flate DecodeParms predictor rows remain complete before WordPress text extraction resumes.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF content tokenizer, inline image scanner, ASCII85/Flate stream decoders, and DecodeParms predictor handling. Full live raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; scanned-PDF OCR, Surya/Texify/Torch model execution, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope for the no-GPU markerPDF lane.
