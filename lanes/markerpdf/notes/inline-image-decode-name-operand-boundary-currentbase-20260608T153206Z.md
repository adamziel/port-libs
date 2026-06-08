# Inline Image Decode Name Operand Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260608T153206Z`
Base: `866ea52a67a61e534ee4668a27bf164b07d3651b`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image handling:
`marker/pdf/extract_text.py` delegates text blocks to the PDF text layer, while
image rendering/review is handled on the image side. This no-GPU PHP port keeps
that boundary: inline image bytes are excluded from visible text, and malformed
image decode operands remain a review-only native image concern.

PDF inline-image abbreviations are context-specific. `/D` is the `/Decode` key,
but `/Decode` values are numeric arrays, not filter/color-space names. A
producer can still emit bad name operands such as `/D [0 /Fl]` or `/D /Fl`; the
review metadata must preserve those operands as raw invalid decode values rather
than rewriting them through the `/Filter` abbreviation table.

## Behavior

- `PdfImageRenderer` and `PdfTextExtractor` now preserve values for canonical
  inline `/Decode` entries before applying value-name abbreviation expansion.
- Valid `/Filter /Fl` still canonicalizes to `/Filter /FlateDecode`.
- Invalid `/Decode` name operands are marked review-only through existing decode
  component-mismatch handling and fail closed before native raster previews.
- Inline-image payload bytes remain excluded from WordPress-visible text.

Pre-patch probe on this base showed
`/D [0 /Fl] /F /Fl` canonicalized as `/Decode [0 /FlateDecode] /Filter /FlateDecode`.
The new focused assertions lock the corrected
`/Decode [0 /Fl] /Filter /FlateDecode` and scalar `/Decode /Fl` behavior.

## Verification

- `php -l lanes/markerpdf/src/PdfImageRenderer.php`:
  no syntax errors.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`:
  no syntax errors.
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeNameOperandBoundaryCurrentBaseTest.php`:
  no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-name-operand-boundary-currentbase.php`:
  no syntax errors.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeNameOperandBoundaryCurrentBaseTest.php`:
  1 test file, 49 assertions, 0 failures; 2 PASS cases added.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeNameOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeTailDecodeParmsCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeParmsTailFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageColorSpaceAbbreviationBoundaryCurrentBaseTest.php`:
  6 test files, 1144 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-name-operand-boundary-currentbase.php`:
  exits 0 with before/after WordPress paragraphs, preserved `/Decode [0 /Fl]`,
  expanded `/Filter /FlateDecode`, preview-failed-closed metadata, and no inline
  payload text exposure.
- `git diff --check -- lanes/markerpdf`:
  exits 0.

## Non-Overlap

This does not repeat accepted inline-image filter EOD handling, DecodeParms tail
preservation, Identity Crypt prefix handling, CCITT Fax boundaries, valid
numeric `/Decode` preview application, indirect numeric decode operands,
duplicate DecodeParms fail-closed review, or color-space abbreviation expansion.
The only source change is the inline `/Decode` value context boundary for bad
name operands.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PHP PDF
tokenizer, inline-image dictionary canonicalizer, and image preview/review
helpers. No OCR/model/GPU/PDFium/PIL/external converter path is activated.
