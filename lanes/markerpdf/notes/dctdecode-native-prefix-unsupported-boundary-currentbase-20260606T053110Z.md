# markerPDF DCTDecode native-prefix unsupported boundary

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T053110Z`

## Source Truth

- Upstream `sddai/markerPDF` at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through the native text/PDFium extraction path before OCR/layout/model stages.
- PDF stream filter arrays are ordered stacks. For image streams, `/DCTDecode` remains review-only in the native no-GPU PHP lane, but native prefix filters before the DCT stage can still be decoded for boundary and metadata review.
- Unsupported middle filters must fail closed without treating JPEG payload bytes, fake `endstream`, or fake object markers as document text.

## Behavior

This current-base slice adds Image XObject review metadata for a DCTDecode stream with a native prefix filter followed by an unsupported middle filter:

```text
/Filter [/FlateDecode /Crypt /DCTDecode]
/DecodeParms [null null null]
```

Before this patch, the extractor kept the image review-only but did not report that the native `/FlateDecode` prefix had already decoded to the JPEG payload before stopping at unsupported `/Crypt`. After this patch, Image XObject review rows expose:

- `native_prefix_decoded=true`;
- `native_prefix_decoded_length`;
- `native_prefix_decoded_sha256`;
- `native_prefix_decoded_preview_hex`;
- `stopped_before_filter=Crypt`.

The global content stream decoder is unchanged. This metadata is limited to image review rows and nested image review surfaces, matching the no-GPU native parser boundary.

## Red Probe

The red-first focused probe failed on the new extractor assertion:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
1 test files, 618 assertions, 1 failures
```

The failing row had `native_prefix_decoded` missing instead of `true` for the Image XObject review entry.

## Evidence

Focused DCT boundary test:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 639 assertions, 0 failures
```

Adjacent DCT Image XObject review family:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeMaskBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeAlternateImageBoundaryCurrentBaseTest.php
Focused test run: 3 selected test files (root lock skipped)
3 test files, 690 assertions, 0 failures
```

DCT-named focused test set:

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg 'Dct|DCT|dct')
Focused test run: 11 selected test files (root lock skipped)
11 test files, 935 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-native-prefix-unsupported-boundary-currentbase.php
```

The smoke emits `xobject_native_prefix_decoded=true`, `xobject_stopped_before_filter=Crypt`, `renderer_unsupported_filters=["Crypt","DCTDecode"]`, `dctdecode_image_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

## Non-Overlap

This does not repeat accepted direct DCTDecode review-only metadata, inline-image DCT alias review metadata, ColorTransform DecodeParms, missing DecodeParms fail-closed behavior, raw/stale-length JPEG EOI recovery, false EOI/SOS marker recovery, post-EOI surplus clipping, null-filter DecodeParms slots, trailing null slots, ASCIIHex/ASCII85/RunLength/LZW prefix boundary tests, unsupported prefix fail-closed behavior without decoded prefix metadata, malformed filter operands, or renderer-only native-prefix review.

The bounded new behavior is specifically extractor Image XObject review metadata for a native prefix decoded before an unsupported middle filter in a DCTDecode image stream.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary reader, filter stack resolver, DecodeParms slot alignment, Flate/ASCIIHex/ASCII85/RunLength/LZW decoders, explicit `/Crypt /Identity` guard, JPEG boundary scanner, Image XObject review rows, and WordPress smoke path. Full OCR/model parity remains intentionally out of scope under the current no-GPU markerPDF direction and remains gated by pdftext/PDFium, pypdfium2/PIL rendering, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, model downloads, and external OCR/rendering helpers; none were executed.
