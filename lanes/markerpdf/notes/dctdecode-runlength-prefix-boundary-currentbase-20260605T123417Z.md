# markerPDF DCTDecode RunLength Prefix Boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T123417Z`

Base accepted HEAD: `d4106867af0d7368819042418c3d677c6a3c6f90`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image rendering: page text comes from PDF text extraction, while DCT/JPEG image payloads route through the image rendering path. In the no-GPU PHP lane, DCTDecode remains preview-only raster data, but the native PDF parser still owns stream boundary recovery so fake PDF operators inside image bytes cannot become WordPress paragraphs.

This current-base slice covers a distinct explicit-EOD prefix filter: `/Filter [/RunLengthDecode /DCTDecode]`. A stale `/Length` can point at an early RunLength EOD byte followed by a fake `endstream/endobj` and fake text object. The native parser must not reopen text extraction there; Image XObject review must still report the recovered full raw stream and DCT as preview-only.

## Implementation

The accepted current base already contains the native DCT prefix-filter recovery needed for this boundary. This patch adds focused current-base coverage and a WordPress smoke for the RunLength prefix form so the behavior stays countable and non-regressive:

- stream-only fallback text extraction excludes fake RunLength/DCT payload objects;
- page Image XObject review reports `filters=["RunLengthDecode","DCTDecode"]`;
- the review row records `preview_only_filters=["DCTDecode"]`, `native_raster_decode=false`, and `decoded_with_current_filters=false`;
- recovered raw length extends beyond the stale `/Length` fake terminator.

## Verification

Focused run:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php
```

Result: `1 test files, 34 assertions, 0 failures`.

Adjacent DCT sweep:

```sh
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRunLengthPrefixBoundaryCurrentBaseTest.php
```

Result: `4 test files, 448 assertions, 0 failures`.

WordPress smoke:

```sh
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-runlength-prefix-boundary-currentbase.php
```

Result: emits `runlength_eod_before_incomplete_jpeg_rejected=true`, `stale_length_recovered_to_complete_dct_payload=true`, `dctdecode_image_payload_excluded_from_text=true`, `xobject_preview_only_filters=["DCTDecode"]`, `xobject_native_raster_decode=false`, and all model/PDFium/PIL/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted generic DCTDecode review-only metadata, DCT DecodeParms alignment, Flate-prefix DCT recovery, prefix-decoded NUL padding, ASCIIHex early-EOD DCT recovery, direct stale/missing/overdeclared Length recovery, indirect DCT filter ownership, unsupported/Crypt Identity prefixes, APP-segment false EOI handling, inline DCT tokenization, DCT CMYK/YCCK sample planning, CCITT/JPX/JBIG2 image-filter boundaries, or broad stream filter-stack recovery.

The bounded behavior is specifically RunLengthDecode explicit-EOD prefix recovery before a preview-only DCTDecode image stage on the current accepted base.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF stream parser, RunLength filter decoder, DCT/JPEG preview boundary scanner, Image XObject review, and WordPress smoke path. Full JPEG raster decoding, pypdfium/PIL rendering, OCR, Surya/Texify/Torch model execution, and exact upstream image benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
