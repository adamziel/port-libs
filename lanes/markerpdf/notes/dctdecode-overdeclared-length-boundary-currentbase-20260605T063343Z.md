# markerPDF DCTDecode Overdeclared Length Boundary

Session: `port-dev-markerpdf-dctdecode-filter-20260605T063343Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T063343Z`
Base accepted HEAD: `9516f07b7dbc0e31f892b7f1f85e7e8fc034d61d`

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` / `naive_get_text()`, delegating low-level PDF stream and image boundaries to pdftext/PDFium before OCR/layout/model stages.
- Upstream image handling is separate in `marker/pdf/images.py::render_image()`, where PDFium/PIL can rasterize image regions. In this native no-GPU lane, `/DCTDecode` JPEG image payloads are review-only raster data and must not consume later searchable content or leak payload-looking text into WordPress paragraphs.
- PDF DCT streams can be identified by JPEG SOI/EOI framing. When `/Length` is overdeclared and no valid `endstream` exists at the declared byte boundary, a complete JPEG EOI followed by the PDF `endstream` token is the safer native parser boundary than swallowing following indirect objects.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

## Behavior

`PdfTextExtractor` now applies DCT/JPEG EOI stream recovery to overdeclared `/Length` values before the direct-object owner scanner advances past later objects. The existing stale-short-length behavior is preserved: if a later DCT terminator exists at or beyond the declared/generic boundary, that later terminator still wins.

The focused fixture declares a DCT image stream length that extends past the image's real `endstream`, through post-stream object-looking text, and into the next object. Before this patch, the direct-object scan swallowed the following object and WordPress extraction returned only the paragraph before the image. After the patch, the stream closes at JPEG EOI plus `endstream`, the following text object remains parseable, and JPEG/post-stream decoys stay out of visible text.

## Red-First Evidence

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL recovers overdeclared DCTDecode lengths at JPEG EOI before later objects
Expected: ["Before overlong DCT stream","After overlong DCT stream"]
Actual: ["Before overlong DCT stream"]
1 test files, 220 assertions, 1 failures
```

## Verification

Focused DCT test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 243 assertions, 0 failures
```

Adjacent DCT/parser/image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserStreamFilterStackBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
Focused test run: 6 selected test files (root lock skipped)
6 test files, 1578 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-stream-terminator-boundary-currentbase.php
```

The smoke emits `overdeclared_length_recovered_at_jpeg_eoi=true`, `embedded_fake_object_rejected=true`, `overdeclared_paragraphs=["Before Overlong DCT Import","After Overlong DCT Import"]`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Required local checks:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-stream-terminator-boundary-currentbase.php
php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'
git diff --check -- lanes/markerpdf
```

All passed locally.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCT review-only image metadata, direct DCT stale-short length repair, NUL padding after JPEG EOI, prefix-decoded DCT padding, ASCIIHex/ASCII85/Flate prefix filter boundaries, Crypt Identity DCT recovery, APP-segment false EOI handling, inline DCT tokenizer framing, DCT CMYK/YCCK metadata, JPX/JBIG2/CCITT image-filter boundaries, generic filtered stream-stack repair, or FileSpec attachment metadata.

The bounded behavior is specifically overdeclared `/Length` on DCTDecode JPEG streams where the native direct-object owner scanner must stop at complete JPEG EOI plus PDF `endstream` before later objects are swallowed.

## Dependency Closure

No new support component is needed. This reuses the native PHP direct-object scanner, stream dictionary parser, DCT/JPEG preview boundary, text extractor, image XObject review path, and WordPress smoke renderer. Full live JPEG raster parity remains gated on PDFium/pypdfium2/PIL or a future native raster backend; OCR/model execution remains intentionally out of scope and was not run.
