# markerPDF Image XObject Decode Native Raster Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T212147Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T212147Z`
Base accepted HEAD: `d1134e2a181aaf4c0c02f2b0d3b93f388be55ad8`

## Source Truth

Pinned upstream markerPDF separates searchable text extraction from image rendering:

- `marker/pdf/extract_text.py` extracts searchable page text through pdftext/PDFium page text.
- `marker/pdf/images.py` renders page or bbox imagery through PDFium/PIL and converts it to RGB.

Under the current no-GPU PHP markerPDF scope, this slice owns the native parser boundary before any raster backend. An Image XObject `/Decode` array whose range-pair count does not match the resolved color-space component count must stay review-only for native RGB handoff. The image payload may still be decoded for checksums/metadata, but the row must not advertise itself as native-raster-ready.

Upstream references:

- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now blocks `native_raster_decode` when the parsed Image XObject `image_decode` metadata reports a component mismatch. The review row also exposes:

- `image_decode_native_raster_blocked`;
- `image_decode_boundary_policy=reject_image_decode_component_mismatch_for_native_raster`.

Decoded stream length/hash metadata remains available, and the image payload remains excluded from WordPress visible text and serialized review JSON.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDecodeNativeRasterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning:  Undefined array key "image_decode_native_raster_blocked" in .../lanes/markerpdf/tests/PdfImageXObjectDecodeNativeRasterBoundaryCurrentBaseTest.php on line 55
FAIL blocks native raster handoff for Image XObject Decode component mismatches
Values are not identical
Expected: false
Actual: NULL
1 test files, 9 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDecodeNativeRasterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS blocks native raster handoff for Image XObject Decode component mismatches
1 test files, 27 assertions, 0 failures
```

Adjacent Image XObject boundary regression:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDecodeNativeRasterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 2 selected test files (root lock skipped)
2 test files, 1287 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-decode-native-raster-boundary-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=2`, `invoked_image_xobject_count=2`, `valid_decode_native_raster_decode=true`, `mismatch_decode_component_mismatch=true`, `mismatch_decode_native_raster_blocked=true`, `mismatch_decode_boundary_policy=reject_image_decode_component_mismatch_for_native_raster`, `payload_excluded_from_text=true`, and the two expected paragraph blocks.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/tests/PdfImageXObjectDecodeNativeRasterBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectDecodeNativeRasterBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-decode-native-raster-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-decode-native-raster-boundary-currentbase.php
```

```text
php -r '$p="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($p), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "lane-status json valid\n";'
lane-status json valid
```

```text
git diff --check -- lanes/markerpdf
```

The diff whitespace check exits 0.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused behavior tests: `3500 -> 3501` pass / `0` fail.
- New focused test: `1` PASS case / `27` assertions after the source fix.
- WordPress scenario count: `2834 -> 2835`.
- Mapped upstream denominator: unchanged; this refines the existing Image XObject `/Decode` boundary row instead of adding a new upstream benchmark unit.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject resource expansion, optional-content hiding, page clipping, page rotation/UserUnit placement, exact-generation SMask/Mask/Metadata/Alternates review, ColorKey masks, named ColorSpace review, ExtGState review, JPX `SMaskInData`, DCT/CCITT/JPX/JBIG2 filter review, inline image Decode previews, renderer-level sample mapping, or the earlier top-level `image_decode` metadata exposure.

The bounded behavior is only blocking native raster readiness when Image XObject `/Decode` component counts do not match the resolved color-space components.

## Dependency Closure

No new support component is needed. This patch reuses the native PHP PDF object parser, stream decoder, Image XObject review row, Decode review helper, focused PHP test harness, and WordPress smoke renderer.

Full upstream raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend, while live OCR, Surya, Texify, Torch/model workers, and external PDF tools remain intentionally out of scope.
