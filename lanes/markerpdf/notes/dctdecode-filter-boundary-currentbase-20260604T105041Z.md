# markerPDF DCTDecode Filter Review Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260604T105041Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260604T105041Z`
Base accepted HEAD: `bd1adef6c04707fd913812d11bba085d56bdc8a6`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` via `pdftext.extraction.dictionary_output(...)`, and image rendering through `marker/pdf/images.py::render_image()` via pypdfium page rendering followed by PIL RGB conversion:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

The native PHP lane does not execute pypdfium, PIL, OCR, Torch, or model workers. Therefore `/DCTDecode` and `/DCT` JPEG image streams must remain review-only raster boundaries in native image metadata, while their payload bytes stay excluded from visible WordPress text.

## Native Behavior Added

`PdfImageRenderer` now classifies `/DCTDecode` and `/DCT` as preview-only image filters in the same public image filter boundary used for JPX, JBIG2, and CCITT:

- `image_filter_details[*].preview_only=true` for DCT filters.
- `image_filter_boundary.preview_only_filters` includes `DCTDecode`.
- `image_filter_boundary.native_raster_decode=false` for DCT image and inline-image review metadata.
- Review notes include `dctdecode_image_filter_review_only` and `inline_dct_image_filter_review_only`.

This does not add JPEG raster decoding. Existing DCT CMYK/YCCK transform and `/Decode` sample review remains metadata-only before a future raster backend.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL marks DCTDecode image filters review-only before RGB preview metadata
FAIL keeps DCT alias inline image review metadata out of native raster decode

1 test files, 4 assertions, 2 failures
```

The failure showed `preview_only=false` for `DCTDecode` and `native_raster_decode=true` for the inline DCT alias after abbreviation expansion.

## Verification

Focused DCT/image/parser family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 622 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-filter-import.php
```

The smoke emitted `preview_only_filters=["DCTDecode"]`, `native_raster_decode=false`, `inline_dct_review_only=true`, `excluded_dctdecode_image_noise=true`, and all Python/model/PDFium/PIL/external-tool execution flags false.

Required local checks:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-filter-import.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCTDecode JPEG stream text exclusion, inline DCT JPEG EOI delimiter validation, DCT CMYK/YCCK Adobe transform planning, DCT `/Decode` sample review, JPX/JBIG2/CCITT image-filter text exclusion, Image XObject payload exclusion, or stream filter-stack recovery.

The new boundary is specifically the public image/inline-image review metadata that previously claimed native raster decode for DCT image filters even though JPEG raster execution is intentionally unavailable in this native no-GPU lane.

## Dependency Closure

No new support component is needed. The slice reuses `PdfImageRenderer`, existing PDF dictionary/filter parsing, inline-image abbreviation expansion, and the WordPress smoke path. Full JPEG raster parity remains gated on pypdfium2/PDFium/PIL or a future native raster backend; no Python, OCR, model, pypdfium, PIL, Poppler, Ghostscript, or other external PDF tools were executed.
