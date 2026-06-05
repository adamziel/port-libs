# markerPDF DCTDecode Post-EOI Surplus Boundary Current Base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T141919Z`

Base accepted HEAD: `498eb9a75cc07113c8bbd92fc3bd97f84c408e6f`

## Source Truth

Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through parser-backed text extraction, while image payloads are rendered through the image path. In this no-GPU PHP lane, `/DCTDecode` JPEG bytes remain review-only and never become WordPress paragraph text.

Relevant upstream boundary:

- `marker/pdf/images.py::render_image()` renders PDF page/image pixels through PDFium/PIL before RGB conversion.

Native PHP does not execute PDFium, PIL, OCR, Torch, Surya/Texify, or external PDF tools for this slice. The bounded source-truth contract is parser-side image review metadata before a future raster backend runs.

## Behavior

Existing DCT stream-boundary recovery handled stale lengths, missing lengths, overdeclared lengths, fake `endstream` tokens before JPEG EOI, native-prefix filters, null filter slots, and malformed filter operands. This slice adds the remaining direct-review boundary where `/Length` points to a syntactically valid `endstream`, but the JPEG payload already reached EOI and non-padding text-like surplus bytes appear before `endstream`.

`PdfTextExtractor` image XObject review rows and `PdfImageRenderer` direct preview rows now:

- preserve existing stream-owner scanning behavior;
- keep fake PDF object/text tokens before JPEG EOI inside the review-only image payload;
- clip non-padding post-EOI surplus from direct DCT review raw lengths;
- leave prefix-encoded DCT raw-byte semantics unchanged;
- keep the WordPress visible text path free of JPEG and post-EOI surplus bytes.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL clips DCTDecode post-EOI surplus from image review bytes before WordPress media handoff
Expected: 22
Actual: 80
1 test files, 387 assertions, 1 failures
```

The failing fixture had a valid declared `endstream`; current review metadata counted the declared 80-byte stream instead of the 22-byte JPEG EOI payload.

## Verification

Focused DCT file:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 404 assertions, 0 failures
```

Adjacent DCT/image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 955 assertions, 0 failures
```

Sampled broad text extractor file:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfTextExtractorTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 625 assertions, 2 failures
```

Those two failures are existing unrelated ToUnicode `usecmap` expectations and are outside this DCT image-review path.

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-post-eoi-boundary-currentbase.php
```

The smoke emits `xobject_post_eoi_surplus_clipped=true`, `renderer_post_eoi_surplus_clipped=true`, `post_eoi_surplus_excluded_from_text=true`, `native_raster_decode=false`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax and whitespace checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-post-eoi-boundary-currentbase.php
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCT review-only filter metadata, DCT CMYK/YCCK color-transform planning, direct fake-endstream-before-EOI recovery, NUL-padded EOI terminators, malformed lenient EOI recovery, missing-length recovery, overdeclared-length recovery before later objects, native-prefix Flate/ASCIIHex/ASCII85/RunLength boundaries, null filter slots, indirect filter owners, unsupported `/Crypt /DCTDecode` handling, malformed filter operands, inline DCT tokenization, CCITT/JPX/JBIG2 preview-only filters, or generic stream filter-stack repair.

The new bounded behavior is specifically direct `/DCTDecode` image review metadata when non-padding surplus bytes follow a complete JPEG EOI before the declared valid `endstream`.

## Dependency Closure

No new support component is needed. This reuses the native PDF stream parser, existing DCT/JPEG EOI scanner, image XObject review rows, direct renderer review rows, focused tests, and WordPress smoke path. Full live JPEG raster parity remains gated on PDFium/pypdfium2/PIL or a future native raster backend; live OCR/model execution remains intentionally out of scope.
