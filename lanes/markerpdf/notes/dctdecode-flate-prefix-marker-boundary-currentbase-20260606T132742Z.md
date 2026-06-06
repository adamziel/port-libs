# markerPDF DCTDecode Flate-prefix marker boundary current-base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260606T132742Z`

Base accepted HEAD: `c004817c65b5e36e22e0d13ad28c2be2d8a34107`

## Source Truth

- Upstream `sddai/markerPDF` is pinned in the lane manifest at `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`.
- Upstream markerPDF hands searchable PDF text through `pdftext`/PDFium before image rendering and model stages. In the native no-GPU PHP lane, DCTDecode JPEG payloads remain image-only review data while text extraction and WordPress paragraph emission must not tokenize raster bytes or fake `endstream` payloads.
- This slice maps the boundary where a native filter prefix such as `/FlateDecode` is applied before `/DCTDecode`: the decoded JPEG bytes should be inspected for SOI/EOI/SOS framing metadata, but DCT itself remains preview-only and never becomes native raster/text decode.

## Implementation

- `PdfTextExtractor` now derives `dctdecode_stream_boundary` from decoded native-prefix bytes when a safe native filter precedes `/DCTDecode` or `/DCT`.
- `PdfImageRenderer` mirrors the same decoded-prefix boundary metadata for media preview review rows.
- Existing raw stream length and `native_prefix_decoded` metadata remain intact; the new boundary adds `review_stream_decoded_from_native_prefix`, `native_prefix_filters`, and `stopped_before_filter` so review consumers can distinguish decoded-prefix JPEG framing from raw DCT streams.

## Verification

Focused test:

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
```

Result:

```text
1 test files, 702 assertions, 0 failures
```

WordPress smoke:

```bash
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-flate-prefix-marker-boundary-currentbase.php
```

Result: emitted two WordPress paragraph blocks and a metadata comment with `review_stream_decoded_from_native_prefix=true`, `native_prefix_filters=["FlateDecode"]`, `stopped_before_filter="DCTDecode"`, `jpeg_marker_framing_used=true`, `sos_marker_seen=true`, `byte_stuffed_ff00_seen=true`, `restart_marker_seen=true`, `renderer_boundary_recorded=true`, and no Python/models/PDFium/PIL/external PDF tools.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted DCTDecode CMYK/YCCK color transform preview planning, direct raw DCT JPEG EOI recovery, APP/SOS/comment/post-EOI DCT stream terminator recovery, null-filter/DecodeParms alignment, duplicate filter/decode-parameter fail-closed review, inline image DCT tokenization, or ASCII85/LZW/RunLength prefix ownership recovery. The bounded new behavior is only decoded native-prefix JPEG marker boundary metadata for Flate-wrapped DCT image streams in parser and renderer review rows.

## Dependency Closure

No new support component is needed. This reuses the native PHP stream-filter decoder, PDF object parser, DCT marker scanner, image XObject review path, renderer preview metadata path, and WordPress smoke pattern. Full upstream OCR/model/PDFium/Torch/Surya/Texify/table visual recognition parity remains intentionally out of scope under the current no-GPU markerPDF direction.
