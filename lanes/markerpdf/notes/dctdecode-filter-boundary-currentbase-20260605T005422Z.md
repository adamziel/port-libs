# markerPDF DCTDecode Filter Boundary Current Base

Session: `port-dev-markerpdf-dctdecode-filter-20260605T005422Z`
Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T005422Z`
Base accepted HEAD: `c39e6ef5dc53ab6c10abe3cd85218cbaaa83096e`

## Source Truth

- Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py::get_text_blocks()` and `naive_get_text()`, delegating low-level stream/image boundaries to pdftext/PDFium before OCR/layout/model stages.
- Upstream markerPDF renders image regions through `marker/pdf/images.py::render_image()` and converts previews to RGB through PDFium/PIL. In this native no-GPU scope, DCTDecode/JPEG payload bytes remain review-only and must not be scanned as visible WordPress text.
- PDF DCTDecode streams are JPEG data. JPEG EOI `FF D9` closes the preview payload; trailing padding bytes before the PDF `endstream` token are not searchable text.

Source links:

- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py
- https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py

## Behavior

`PdfTextExtractor` now treats NUL bytes after a DCTDecode JPEG EOI marker as preview padding before the real PDF `endstream` token. This matches the existing prefix-filter JPEG completeness boundary and prevents missing-`/Length` or stale short-`/Length` image streams from accepting a fake `endstream/endobj` sequence embedded inside JPEG bytes.

Red-first probe before the source change:

```text
missing-Length DCT stream => Before Padded DCT / Padded DCT Payload Leak / After Padded DCT
stale-Length DCT stream => Before Padded DCT / Padded DCT Payload Leak / After Padded DCT
```

After the patch, both cases return only the surrounding searchable paragraphs and exclude `JFIF`, `endstream`, and the fake payload object text.

## Evidence

Focused DCT test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS marks DCTDecode image filters review-only before RGB preview metadata
PASS keeps DCT alias inline image review metadata out of native raster decode
PASS records DCTDecode ColorTransform DecodeParms on image XObject review rows
PASS keeps DCTDecode JPEG endstream decoys inside image payload boundaries
PASS keeps NUL-padded DCTDecode JPEG EOI boundaries before fake endstream payloads
PASS keeps Flate-wrapped DCTDecode JPEG endstream decoys inside image payload boundaries

1 test files, 59 assertions, 0 failures
```

Adjacent parser/image family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
4 test files, 1211 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-stream-terminator-boundary-currentbase.php
```

The smoke emits `nul_padded_jpeg_eoi_boundary=true`, `embedded_fake_object_rejected=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted generic DCTDecode image-filter exclusion, inline DCT tokenizer framing, DCTDecode CMYK Adobe transform/Decode review, ASCII85/Flate prefix-filter DCT boundaries, stream-filter DecodeParms fail-closed behavior, CCITT/JPX/JBIG2 preview-only filters, or image XObject RGB preview metadata. The new behavior is specifically direct DCTDecode JPEG EOI padding before PDF `endstream` selection.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP PDF stream scanner, filter resolver, content-token parser, and WordPress smoke path. Full live raster parity remains out of scope under the current no-GPU markerPDF direction and remains gated on pypdfium/PIL or a future native raster backend; this patch does not execute Python, models, external PDF tools, pypdfium, or PIL.
