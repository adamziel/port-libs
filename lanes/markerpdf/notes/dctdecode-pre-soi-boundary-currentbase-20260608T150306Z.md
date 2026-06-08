# markerPDF DCTDecode Pre-SOI Boundary Current Base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T150306Z`

## Source Truth

Upstream `sddai/markerPDF` routes searchable text through PDF text extraction while image bytes go through image rendering. In this native no-GPU PHP lane, `/DCTDecode` JPEG streams remain review-only unless a future raster backend proves pixel decoding. A DCT stream with arbitrary non-padding bytes before the JPEG SOI marker should therefore stay fail-closed: the bytes are image-owned and must not become WordPress paragraphs, but review metadata should distinguish this from a truly missing SOI.

## Change

- `PdfTextExtractor` and `PdfImageRenderer` now classify DCT streams with later SOI markers behind non-padding pre-SOI bytes as `pre_jpeg_soi_non_padding_bytes`.
- The boundary remains `dctdecode_jpeg_marker_boundary_unverified`, with `valid_jpeg_marker_boundary=false` and `native_raster_decode=false`.
- Review metadata records `pre_jpeg_soi_byte_count`, `pre_jpeg_soi_sha256`, `stream_trimmed_to_jpeg_soi=false`, and `pre_jpeg_soi_payload_in_visible_text=false` without exposing the pre-SOI text bytes.
- WordPress import keeps only surrounding PDF text paragraphs and excludes fake text embedded before JPEG SOI.

## Evidence

Red check before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePreSoiBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL records pre-SOI DCTDecode garbage as image-owned before WordPress media review
1 test files, 17 assertions, 1 failures
```

Focused test after implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePreSoiBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS records pre-SOI DCTDecode garbage as image-owned before WordPress media review
1 test files, 44 assertions, 0 failures
```

DCT adjacent family:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Dct|DCT|Jpeg|JPEG')
Focused test run: 32 selected test files (root lock skipped)
32 test files, 1903 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-pre-soi-boundary-currentbase.php
```

The smoke emits `pre_jpeg_soi_invalid_reason=pre_jpeg_soi_non_padding_bytes`, `renderer_pre_jpeg_soi_invalid_reason=pre_jpeg_soi_non_padding_bytes`, `pre_jpeg_soi_payload_excluded_from_text=true`, `native_raster_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Final syntax and lane hygiene:

```text
php -l lanes/markerpdf/src/PdfImageRenderer.php
No syntax errors detected in lanes/markerpdf/src/PdfImageRenderer.php

php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfDctDecodePreSoiBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfDctDecodePreSoiBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-pre-soi-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-dctdecode-pre-soi-boundary-currentbase.php

php -r 'foreach (["lanes/markerpdf/lane-status.json", "lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json"] as $file) { json_decode((string) file_get_contents($file), true, 512, JSON_THROW_ON_ERROR); echo $file . " JSON OK\n"; }'
lanes/markerpdf/lane-status.json JSON OK
lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json JSON OK

git diff --check -- lanes/markerpdf
```

`git diff --check -- lanes/markerpdf` exited 0 with no output.

## Non-Overlap

This does not repeat accepted DCT review-only filter metadata, DCT CMYK/YCCK color-transform planning, BOM/marker-fill SOI padding, APP/SOS marker parsing, fake `endstream` before EOI recovery, post-EOI surplus clipping, missing/no-EOI malformed-stream review, native prefix filters, null/trailing filter slots, malformed filter operands, inline DCT tokenization, or CCITT/JPX/JBIG2 preview-only image filters.

The bounded behavior is specifically arbitrary non-padding bytes before a later JPEG SOI marker in a direct DCTDecode image stream: the stream remains unverified/fail-closed while pre-SOI byte ownership is visible as review metadata and excluded from WordPress text.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, DCT/JPEG marker scanner, Image XObject review rows, direct renderer preview rows, focused test harness, and WordPress smoke path. Full JPEG raster decoding, PDFium/pypdfium2/PIL parity, live OCR, Surya/Texify/Torch model execution, and external PDF tools remain intentionally outside the current no-GPU markerPDF scope.
