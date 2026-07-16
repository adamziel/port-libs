# markerPDF Inline Filter Palette Alpha Current Base

Micro-slice: `image-inline-filter-palette-alpha-currentbase`

Base accepted HEAD: `2bf77cd5f648f9f608014de847ea7b020b711784`

## Source Truth

Upstream `sddai/markerPDF` is pinned in `UPSTREAM_TEST_MANIFEST.json` at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.

- `marker/pdf/images.py::render_image()` renders pages through PDFium, then converts the PIL output to RGB. The native PHP boundary therefore prepares RGB image-preview metadata but does not execute PDFium/PIL.
- `marker/pdf/extract_text.py::naive_get_text()` reads text through PDFium text pages, so inline image payload bytes must remain outside visible text extraction.
- Relevant PDF image behavior for this slice: inline image dictionaries can use abbreviated keys/values; supported stream filters must be applied before sample interpretation; `/Indexed` sample values map through `/Decode` and the palette before RGB preview; `/SMask` contributes alpha when its current-object stream is decodable.

## Implementation

Added `PdfImageRenderer::inlineIndexedImageStreamPreviewRows()`.

The method:

- expands inline image abbreviations through the existing canonical dictionary path;
- decodes supported inline filter chains such as `ASCIIHexDecode` plus `FlateDecode`;
- expands `/Indexed` palette rows using the current `/Decode` plan;
- applies current-object soft-mask samples and transfer metadata before RGB preview;
- preserves preview-only raster filters such as `JPXDecode` as review-only while still decoding supported current soft-mask streams;
- keeps inline payload bytes excluded from visible text metadata.

The implementation reuses the existing parser, filter decoders, packed sample reader, Indexed palette helpers, ColorKey helper, and soft-mask planner.

## WordPress Smoke

Added `examples/wordpress-pdf-inline-filter-palette-alpha-currentbase.php`.

The smoke emits a Gutenberg image block carrying:

- `data-marker-color-space="Indexed"`
- `data-marker-inline-filter-chain="ASCIIHexDecode,FlateDecode"`
- `data-marker-soft-mask-alpha="current-object"`
- palette row and alpha metadata for red and blue preview swatches

It fails fast if the decoded palette indexes or soft-mask alpha values do not match the expected RGB review rows.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php`
  - PASS decodes inline Indexed filter payloads with palette and current soft-mask alpha before RGB preview
  - PASS keeps preview-only inline Indexed filters review-only while preserving palette alpha metadata
  - Result: `1 test files, 66 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-filter-palette-alpha-currentbase.php` passed and emitted the expected Gutenberg image block metadata.

Additional final verification is recorded in the handoff response.

## Status Delta

- Focused PHP PASS lines: `781 -> 783`
- Mapped semantics: `555 -> 556 / 78`
- Updated `lane-status.json` and `UPSTREAM_TEST_MANIFEST.json` for this isolated handoff.

## Non-Overlap

This does not repeat accepted inline ImageMask sample previews, inline JPX payload boundary validation, inline filter-array abbreviation/null-entry boundaries, Indexed XObject ColorKey transfer, Indexed default Decode/soft-mask previews, Indexed ICC/JBIG2 soft-mask review, Indexed Separation/DeviceN transfer review, DeviceGray/DeviceN/Calibrated image stream rows, named color-space soft-mask review, DCT CMYK Decode planning, or generic stream-filter fail-closed work. The new behavior is specifically inline Indexed image payload filter decoding plus palette/alpha review rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF dictionary parser, inline image canonicalization, stream-filter decoders, Indexed palette lookup, packed image sample decoding, and soft-mask stream review. Full upstream runner parity remains blocked by the Python/model stack: PDFium/pypdfium2, PIL, pdftext, Surya/Torch models, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, benchmark tooling, and external OCR/PDF helpers.
