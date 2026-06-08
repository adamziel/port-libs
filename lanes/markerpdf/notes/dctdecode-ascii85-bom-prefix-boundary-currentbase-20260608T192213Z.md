# DCTDecode ASCII85 BOM Prefix Boundary Current Base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T192213Z`

Base accepted HEAD: `e97bdf9331ef05dac3f6237d837a28df8dd53eb5`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction in the PDF text path and routes page/image rendering through the image path. See:

- `marker/pdf/extract_text.py`: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/extract_text.py`
- `marker/pdf/images.py`: `https://raw.githubusercontent.com/sddai/markerPDF/da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34/marker/pdf/images.py`

In the current no-GPU PHP port scope, DCTDecode/JPEG image bytes remain review-only image payloads. Native prefix filters may be decoded only far enough to prove stream ownership and image metadata; they must not become visible text or native raster/model execution.

## Red-First Probe

Before the renderer source edit, a local fixture using `/Filter [/ASCII85Decode /DCTDecode]` with a UTF-8 BOM before the ASCII85 `<~ ... ~>` member produced safe text, but the renderer could not decode the native prefix for review metadata:

```text
entry_raw=49 entry_boundary_source=null entry_decoded=null
renderer_raw=49 renderer_boundary_source=null renderer_decoded=null
expected_raw=49
```

The visible text extractor already kept the raw stream out of text; the gap was the renderer review handoff, which could not surface decoded-prefix DCT/JPEG metadata for a BOM-prefixed ASCII85 member.

## Implementation

`PdfImageRenderer` now decodes a DCT native prefix that starts with UTF-8 BOM bytes immediately before an ASCII85 member. The tolerance is bounded to DCT review paths:

- the main image stream decoder only uses it when recording a native prefix before `DCTDecode`;
- the DCT boundary review helper uses it while decoding filters before the preview-only DCT step;
- generic ASCII85 decoding remains unchanged for non-DCT image streams.

DCT remains review-only: `decoded_with_current_filters=false`, `native_raster_decode=false`, no pixels are emitted, and no OCR/model/PDFium/PIL path is invoked.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeAscii85BomPrefixBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS keeps ASCII85 BOM prefix DCTDecode streams review-only and out of text

1 test files, 46 assertions, 0 failures
```

This adds 1 focused PASS case and 46 focused assertions.

```text
php tools/run-tests.php $(rg --files lanes/markerpdf/tests | rg '/PdfDctDecode.*Test\.php$' | sort)
Focused test run: 34 selected test files (root lock skipped)
...
34 test files, 2055 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-ascii85-bom-prefix-boundary-currentbase.php
```

The smoke emitted `bom_prefixed_ascii85_member_excluded_from_visible_text=true`, `bom_prefixed_ascii85_member_decoded_for_renderer_review=true`, `renderer_boundary_source="dctdecode_jpeg_marker_boundary"`, `renderer_boundary_decoded_from_native_prefix=true`, `decoded_with_current_filters=false`, `native_raster_decode=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct DCT stream exclusion, raw DCT BOM/marker-fill SOI recovery, inline padded DCT SOI handling, DCT APP/SOS marker parsing, ASCII85 early-EOD multi-member recovery, Flate/LZW/ASCIIHex/RunLength native-prefix DCT boundaries, null/trailing DecodeParms slot alignment, duplicate/malformed filter operands, Crypt Identity handling, post-DCT filters, CCITT/JPX/JBIG2 preview-only filters, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded new behavior is specifically renderer-side DCT review metadata when the native ASCII85 member itself starts after UTF-8 BOM bytes.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, image filter stack resolver, ASCII85 decoder, JPEG/DCT marker boundary scanner, `PdfTextExtractor`, `PdfImageRenderer`, and WordPress smoke path. Full OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive and remains gated by pdftext/PDFium, pypdfium2/PIL rendering, Surya/Torch, tabled-pdf, Texify, Streamlit/FastAPI runtime paths, model downloads, and external OCR/rendering helpers; none were executed.
