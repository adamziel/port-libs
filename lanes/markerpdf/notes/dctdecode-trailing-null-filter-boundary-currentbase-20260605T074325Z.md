# DCTDecode trailing-null filter boundary current-base

## Source truth

- Upstream `sddai/markerPDF` remains pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- markerPDF delegates searchable PDF text extraction and image rendering to pdftext/PDFium-style parser boundaries. In the native no-GPU PHP lane, JPEG/DCT image streams stay review-only before WordPress paragraph extraction, while image filter metadata such as DCT `/DecodeParms /ColorTransform` is preserved for import review.
- PDF filter arrays permit `null` identity filters. A `null` filter's aligned `/DecodeParms` slot must not make the concrete DCT filter's review metadata unresolved.

## Behavior

This slice maps DCTDecode image XObject filter arrays with trailing `null` filter slots:

- visible text extraction keeps JPEG payload bytes, fake `endstream` markers, and fake nested object text out of Gutenberg paragraphs;
- the image review row still reports `DCTDecode` as preview-only and non-raster-decoded;
- DCT `/DecodeParms << /ColorTransform 2 >>` remains attached to the DCT filter even when the trailing `null` filter's DecodeParms slot is an unresolved object reference.

The red-first probe before implementation preserved text exclusion but returned `filter_details[0].decode_parms = null` for `[/DCTDecode null] /DecodeParms [<< /ColorTransform 2 >> 99 0 R]`.

## Changes

- `PdfTextExtractor::imageXObjectBoundaryEntry()` now resolves image XObject `/DecodeParms` with the already parsed filter stack, so DecodeParms entries aligned to `null` identity filters are ignored instead of poisoning concrete DCT review metadata.
- `PdfDctDecodeFilterBoundaryCurrentBaseTest.php` adds one focused PASS case covering stream-only fallback and page-invoked image XObject review.
- `wordpress-pdf-dctdecode-trailing-null-filter-currentbase.php` adds a WordPress smoke showing clean paragraph output, preserved `ColorTransform`, preview-only DCT review, and no Python/model/PDFium/external-tool execution.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`
  - Before this slice: 1 test file / 269 assertions / 0 failures.
  - After this slice: 1 test file / 294 assertions / 0 failures.
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php`
  - 1 test file / 15 assertions / 0 failures.
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  - no syntax errors.
- `php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`
  - no syntax errors.
- `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-trailing-null-filter-currentbase.php`
  - no syntax errors.
- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-trailing-null-filter-currentbase.php`
  - emits `trailing_null_filter_slot_ignored=true`, `dct_decodeparms_color_transform=2`, `native_raster_decode=false`, `decoded_with_current_filters=false`, `excluded_dctdecode_payload_noise=true`, and the two expected WordPress paragraph blocks.

## Non-overlap

This does not repeat accepted raw DCT EOI boundaries, prefix-decoded Flate/ASCIIHex/ASCII85 DCT boundaries, null filter slots before DCT, indirect filter owner boundaries, malformed filter operands, Crypt Identity DCT boundaries, inline DCT tokenizer boundaries, Type3 CharProc resource fallback, CCITT/JBIG2/JPX image boundaries, or general stream-owner/xref repair work. The bounded behavior here is specifically DCTDecode review metadata preservation when DecodeParms entries aligned to trailing `null` identity filters are unresolved.

## Dependency closure

No new support component is needed. This reuses the native PHP PDF object parser, stream dictionary/value parser, filter stack normalization, DecodeParms resolver, image XObject review path, DCT preview-only boundary, and WordPress smoke pattern. Live OCR, Surya/Texify/Torch model execution, PDFium/PIL raster rendering, Streamlit/FastAPI workers, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
