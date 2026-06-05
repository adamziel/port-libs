# markerPDF DCTDecode Alternate Image Boundary Current Base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T145741Z`

Base accepted HEAD: `e652d21271ab12be9ff0611b369141b289c7b5d7`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable PDF text extraction separate from image rendering. Image XObjects, including alternate images, flow through the image-rendering handoff rather than becoming visible text.

Relevant upstream boundary:

- `marker/pdf/images.py::render_image()` handles image raster rendering after the PDF parser identifies image streams.

The no-GPU PHP lane does not execute PDFium, PIL, OCR, Torch, Surya/Texify, or external PDF tools. This slice ports the parser-side review contract for `/DCTDecode` alternate images before a future raster backend.

## Behavior

`PdfTextExtractor` already treated direct DCT image XObjects as review-only and clipped non-padding bytes after the final JPEG EOI marker for image-review raw lengths. Alternate images under `/Alternates` used a separate review path, so DCT alternate image rows kept the full declared stream length and did not expose DCT filter details.

This slice makes alternate image review rows:

- preserve `/DCTDecode` filter details and normalized `DecodeParms` metadata;
- keep DCT alternates review-only with `native_raster_decode=false`;
- clip alternate raw byte lengths at the final JPEG EOI marker when post-EOI surplus appears before `endstream`;
- keep JPEG bytes and post-EOI text-like surplus out of WordPress-visible text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL DCTDecode alternate image review is filter-aware and clips post-EOI surplus bytes (lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php)
Alternate review clips raw bytes at the final JPEG EOI marker.
Expected: 22
Actual: 81

1 test files, 10 assertions, 1 failures
```

The failing fixture used a primary image XObject with an `/Alternates` entry whose alternate stream was `/Filter /DCTDecode`, declared post-EOI surplus before `endstream`, and carried `/DecodeParms << /ColorTransform 0 >>`.

## Verification

Focused alternate DCT test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS DCTDecode alternate image review is filter-aware and clips post-EOI surplus bytes

1 test files, 15 assertions, 0 failures
```

Adjacent DCT/image-XObject family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeCommentReferenceBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 7 selected test files (root lock skipped)
7 test files, 1344 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-alternate-image-boundary-currentbase.php
```

The smoke emits `alternate_post_eoi_surplus_clipped=true`, `alternate_payload_excluded_from_text=true`, `dct_decodeparms_color_transform=0`, `native_raster_decode=false`, `decoded_with_current_filters=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-alternate-image-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-dctdecode-alternate-image-boundary-currentbase.php

git diff --check -- lanes/markerpdf
```

All syntax and whitespace checks passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct DCT post-EOI clipping, DCT soft-mask boundaries, DCT CMYK/YCCK color-transform rendering plans, malformed DCT filter operand boundaries, indirect DCT filter ownership, prefix-filter DCT boundaries, inline DCT tokenization, DCT segment-length scanning, lenient/missing/overdeclared DCT stream-length recovery, CCITT/JPX/JBIG2 preview-only filters, or GPU/OCR/model behavior.

The new bounded behavior is specifically `/DCTDecode` image XObjects referenced from `/Alternates`, where the nested alternate review row needs the same review-only DCT filter metadata and JPEG EOI raw-byte boundary as top-level image review rows.

## Dependency Closure

No new support component is needed. This reuses the native PDF stream parser, existing JPEG EOI scanner, existing `DecodeParms` normalization, image XObject review rows, focused PHP tests, and a WordPress smoke. Full live raster parity remains gated on a future native raster/PDFium/PIL handoff; live OCR/model execution remains intentionally out of scope.
