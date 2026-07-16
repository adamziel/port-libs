# markerPDF DCTDecode Native-Prefix Post-EOI Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T074552Z`
Base accepted HEAD: `abd1af5843ccdf0a6730b63402c30abf96a3e9f7`

## Source Truth

Upstream `sddai/markerPDF` at the pinned manifest commit routes searchable text extraction separately from PDF image rendering. In this no-GPU PHP lane, DCTDecode JPEG bytes remain review-only image payloads, but native prefix filters such as `/FlateDecode` still need to be decoded before the DCT marker boundary is reviewed.

The accepted raw DCT post-EOI slice clipped direct JPEG review bytes at EOI. This slice covers the non-overlapping native-prefix case: `/Filter [/FlateDecode /DCTDecode]` where Flate expands to a complete JPEG followed by non-padding text-like surplus before `endstream`.

## Red First

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL clips native-prefix decoded DCTDecode post-EOI surplus before image review handoff
Expected: 'dctdecode_jpeg_marker_boundary'
Actual: 'dctdecode_jpeg_marker_boundary_unverified'
1 test files, 19 assertions, 1 failures
```

## Implementation

- `PdfTextExtractor` now clips decoded native-prefix review bytes to the JPEG EOI before producing DCT marker-boundary metadata.
- `PdfImageRenderer` mirrors the same boundary behavior for standalone image stream preview metadata.
- Raw encoded stream lengths and native-prefix decoded lengths remain visible as review metadata, while the DCT review stream length reflects only the JPEG payload handed to the preview-only DCT boundary.
- WordPress paragraph extraction remains clean; neither JPEG bytes nor post-EOI surplus text becomes visible text.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS clips native-prefix decoded DCTDecode post-EOI surplus before image review handoff
1 test files, 53 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeMalformedStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1403 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-native-prefix-post-eoi-boundary-currentbase.php
```

The smoke emitted `stream_filters=["FlateDecode","DCTDecode"]`, `xobject_prefix_post_eoi_surplus_clipped=true`, `renderer_prefix_post_eoi_surplus_clipped=true`, `post_eoi_surplus_excluded_from_text=true`, `review_stream_decoded_from_native_prefix=true`, `native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax checks passed for:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeNativePrefixPostEoiBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-native-prefix-post-eoi-boundary-currentbase.php
```

```text
git diff --check -- lanes/markerpdf
```

Passed with no whitespace errors.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct raw DCT post-EOI surplus clipping, Flate/LZW/ASCII85/RunLength early-EOD prefix recovery, decoded-prefix marker metadata without surplus, raw DCT fake endstream recovery, APP/SOS segment boundaries, inline DCT tokenization, DecodeParms alignment/fail-closed behavior, duplicate filter handling, Crypt Identity, CCITT/JPX/JBIG2 preview-only filters, or raster/model execution. The new boundary is specifically decoded native-prefix DCT review bytes with post-EOI surplus.

## Dependency Closure

No new support component is needed. This reuses the native PHP stream-filter decoder, DCT/JPEG marker scanner, image XObject review path, standalone image renderer metadata path, and WordPress smoke path. Full JPEG raster parity through PDFium/pypdfium/PIL, OCR, Surya/Texify/Torch, and exact upstream model benchmarks remain intentionally outside this no-GPU markerPDF slice.
