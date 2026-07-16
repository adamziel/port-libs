# markerPDF DCTDecode Prefix Padding Boundary

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates page text to `pdftext.dictionary_output()` and `naive_get_text()` delegates page text to PDFium text pages: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>
- Upstream image rendering is separate in `marker/pdf/images.py::render_image()`, which renders through PDFium/PIL and converts page images to RGB: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py>
- Under the current no-GPU markerPDF scope, DCTDecode/JPEG bytes stay review-only raster payloads. The native parser still owns the boundary that prevents JPEG-looking bytes and fake PDF object tokens from becoming WordPress paragraphs.

## Behavior

The accepted direct DCTDecode boundary already treated leading and trailing NUL bytes around JPEG SOI/EOI as preview padding. Prefix-decoded DCT streams, such as `/Filter [/FlateDecode /DCTDecode]`, did not apply the same leading-NUL rule after decoding the prefix filter. A missing or stale `/Length` could then accept a fake raw `endstream` marker inside the encoded stream and leak text-looking JPEG payload bytes into visible WordPress text.

`PdfTextExtractor::dctPreviewBytesAreCompleteJpeg()` now skips leading NUL bytes before checking for JPEG SOI after prefix filters have been decoded. This aligns prefix-filter DCT streams with direct DCT streams while keeping JPEG raster decode out of scope.

## Red-First Evidence

Before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
FAIL keeps prefix-decoded NUL-padded DCTDecode JPEG boundaries before fake endstream payloads
Expected: ["Before padded Flate DCT stream","After padded Flate DCT stream"]
Actual: ["Before padded Flate DCT stream","Padded Flate DCT payload leak","After padded Flate DCT stream"]
1 test files, 60 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
1 test files, 73 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfTextExtractorTest.php
6 test files, 1371 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-dctdecode-flate-prefix-boundary-currentbase.php
emits prefix_decoded_nul_padded_jpeg_boundary=true, missing_length_fake_endstream_rejected=true, stale_length_fake_endstream_rejected=true, and paragraphs ["Before Flate DCT Import","After Flate DCT Import"].
```

## Non-Overlap

This does not repeat generic DCTDecode review-only image metadata, inline DCT JPEG EOI tokenization, DCT CMYK/YCCK Adobe transform planning, DCT `/Decode` sample review, direct DCT JPEG EOI padding, ASCII85 explicit EOD prefix handling, Flate prefix decoding without leading decoded padding, CCITT/JPX/JBIG2 preview-only filters, or broad stream filter-stack recovery.

The bounded behavior is specifically prefix-decoded DCTDecode JPEG completeness when the decoded JPEG bytes have leading NUL padding before SOI and trailing padding after EOI.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF stream filter decoders, DCT preview boundary, content stream scanner, and WordPress smoke renderer. Full JPEG raster parity remains dependency-gated on PDFium/pypdfium2/PIL or a future native raster backend; OCR/model execution remains intentionally out of scope and was not run.
