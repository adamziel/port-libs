# Outline Metadata Color Boundary Current Base

Micro-slice: `markerpdf-outline-metadata-boundary-current-base-20260604T092445Z`

Source truth:

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` gets PDF TOC rows through the document outline adapter and treats them as navigation metadata instead of visible page text.
- PDF outline item dictionaries use `/F` for style flags and `/C` for three DeviceRGB text-color components. The existing native outline review path already preserved `/C`; this slice brings the document-level `PdfMetadataExtractor::document_outline` metadata into parity.

Implementation:

- `PdfMetadataExtractor` now records outline item `/C` as `text_color_rgb` and `text_color_hex` on document outline metadata rows.
- Color arrays may be direct or indirect and each component is clamped to the PDF unit range `0..1`, matching the existing richer outline extractor behavior.
- `wordpress-pdf-outline-metadata-boundary-currentbase.php` now exposes the colors as WordPress navigation review attributes while keeping outline metadata out of visible paragraph text.

Focused evidence:

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php` failed with missing `text_color_rgb` on the current outline metadata row.
- After implementation: `php tools/run-tests.php lanes/markerpdf/tests/PdfOutlineMetadataBoundaryCurrentBaseTest.php` passed with 1 file, 79 assertions, 0 failures.
- `php lanes/markerpdf/examples/wordpress-pdf-outline-metadata-boundary-currentbase.php` passed and emitted `outline_text_colors=["#0059b3","#0080ff"]`.

Status delta:

- `phpPass` moves `1041 -> 1042` from the added focused color-boundary PASS case.
- Assertion coverage for the focused outline metadata boundary test moves to 79 assertions.

Non-overlap:

- This does not repeat PageLabels inherited-Limits, named destination resolution, outline destination action context, page transition/action enrichment, or the richer `PdfOutlineExtractor` color path. The new behavior is document-level catalog `/Outlines` metadata color preservation in `PdfMetadataExtractor`.

Dependency closure:

- No new support component is needed. The slice reuses the native PDF object parser, indirect operand resolver, catalog outline metadata extraction, and WordPress smoke path without Python, pdftext, pypdfium/PDFium, OCR, model execution, or external PDF tools.
