# Inline Image Color-Space Abbreviation Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260608T145930Z`

Source truth: PDF inline image dictionaries use abbreviated keys and selected abbreviated values, while upstream markerPDF keeps inline image payloads out of extracted text and hands image rendering to the image path. This native no-GPU slice keeps that boundary by expanding color-space family/alternate abbreviations without rewriting `/DeviceN` or `/Separation` colorant identifiers that happen to be named like inline abbreviations.

Implementation:

- `PdfTextExtractor` now canonicalizes inline image color-space arrays by operand context before tokenizer sample-boundary checks.
- `PdfImageRenderer` now reports canonical inline image dictionaries that preserve DeviceN/Separation colorant names such as `/I` and `/RGB`, while still expanding `/CMYK` alternates and filter/color-space families.
- Added a focused WordPress smoke proving payload text is excluded and no Python, OCR, model, raster, or external PDF tool path executes.

Verification:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageColorSpaceAbbreviationBoundaryCurrentBaseTest.php` => 1 test file / 19 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImage*.php lanes/markerpdf/tests/PdfImageInline*.php lanes/markerpdf/tests/PdfParserInline*.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfFontType3CharProcsInlineImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcCMapBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcRepeatBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectType3CharProcResourceTailBoundaryCurrentBaseTest.php` => 28 test files / 2435 assertions / 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-colorspace-abbreviation-boundary-currentbase.php` => exits 0; inline payload text excluded, DeviceN colorants preserved, `/CMYK` alternate expanded, no Python/models/external PDF tools.
- `php -l` for changed PHP files passed.
- `git diff --check -- lanes/markerpdf` => passed.

Dependency closure: no new support component is needed. The patch reuses the existing native content-stream tokenizer and image renderer metadata planner.
