# markerPDF DCTDecode native-prefix alias boundary

## Source Truth

- Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text through `marker/pdf/extract_text.py`, where `get_text_blocks()` delegates page text to `pdftext.dictionary_output()` and `naive_get_text()` delegates page text to PDFium text pages: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py>
- Upstream image rendering remains separate in `marker/pdf/images.py::render_image()`, which renders page/image regions through PDFium/PIL before RGB conversion: <https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py>
- Under the current no-GPU markerPDF scope, DCTDecode/JPEG bytes stay review-only image payloads. The native PHP parser owns the boundary metadata that keeps JPEG bytes out of WordPress paragraphs while preserving enough filter-stack detail for media review.

## Behavior

PDF image streams can use filter aliases in filter arrays. The existing DCTDecode boundary preserved native prefix aliases such as `/Fl` in `native_prefix_filters`, but unlike the CCITT filter boundary it did not expose the canonical decoder name. WordPress review then saw that a prefix stage existed but could not tell that `/Fl` was the native FlateDecode stage before terminal `/DCT`.

`PdfImageRenderer` and `PdfTextExtractor` now add `canonical_native_prefix_filters` to DCTDecode boundary metadata when the canonical native-prefix list differs from the source filter list. The source aliases remain intact for PDF review:

- `native_prefix_filters=["Fl"]`
- `canonical_native_prefix_filters=["FlateDecode"]`
- terminal DCT source alias `/DCT` remains `declared_filter="DCT"` and `canonical_filter="DCTDecode"`

The focused fixture uses `/Filter [/Fl /DCT]` with a Flate-wrapped JPEG payload that contains text-looking bytes. The prefix is decoded only for boundary review, the parser stops before DCTDecode, and JPEG bytes remain excluded from visible WordPress text and raster decoding.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeNativePrefixAliasBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS reports canonical native prefix aliases before terminal DCTDecode image review

1 test files, 38 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-native-prefix-alias-boundary-currentbase.php
```

The smoke exits 0 and emits `filters=["Fl","DCTDecode"]`, `preview_only_filters=["DCTDecode"]`, `native_prefix_filters=["Fl"]`, `canonical_native_prefix_filters=["FlateDecode"]`, `review_stream_decoded_from_native_prefix=true`, `stopped_before_filter="DCT"`, `dctdecode_payload_excluded_from_text=true`, and all Python/model/PDFium/PIL/external-tool execution flags false.

## Non-Overlap

This does not repeat accepted DCT review-only filter metadata, DCT alias-only metadata, escaped filter names, direct fake-endstream recovery, BOM/NUL/padded SOI/EOI boundaries, malformed stream boundaries, APP/SOS marker scanning, post-EOI surplus clipping, native Flate/ASCIIHex/ASCII85/RunLength/LZW prefix EOD boundaries, post-DCT filters, indirect filter owner/tail rejection, DecodeParms owner/alignment behavior, CMYK/YCCK color-transform planning, inline DCT tokenization, CCITT/JPX/JBIG2 preview-only filters, OCR/model execution, or native raster decoding.

The bounded behavior is specifically canonical native-prefix filter metadata for aliased DCTDecode filter stacks before WordPress image review.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object parser, filter-stack resolver, Flate stream decoder, DCT preview boundary review, Image XObject review rows, and WordPress smoke renderer. Full JPEG raster parity remains gated on PDFium/pypdfium2/PIL or a future native raster backend; OCR/model execution remains intentionally out of scope and was not run.
