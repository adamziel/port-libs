# DCTDecode ASCII85 Prefix Boundary Current Base

Micro-slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T195936Z`

Base accepted HEAD: `23ac5b361540ae4c76b2fbb0d32c27d96db41cc5`

## Source Truth

Upstream `sddai/markerPDF` commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` routes searchable PDF text extraction and image handoff through parser-backed PDF dependency behavior before model/OCR stages. In the current no-GPU markerPDF scope, DCTDecode/JPEG image streams remain preview-only raster payloads: text extraction and WordPress media review must keep embedded fake `endstream`/`endobj` owner bytes inside the image stream until the encoded filter prefix reaches a complete JPEG boundary.

This slice covers the native PHP image-review side of an ASCII85-prefixed DCTDecode stream. The visible text extractor already recovered the later boundary, but the direct `PdfImageRenderer` review path stopped at the stale first ASCII85 member before the complete JPEG member.

## Red-First Probe

Before the source edit, a local probe using `/Filter [/ASCII85Decode /DCTDecode]` with a stale `/Length` at the first ASCII85 `~>` member reported:

```text
lines=["Before ASCII85 DCT stream","After ASCII85 DCT stream"]
text="Before ASCII85 DCT stream\nAfter ASCII85 DCT stream"
raw=188 expected=188 fake=31
filters=["ASCII85Decode","DCTDecode"] decoded=false native_prefix=null
renderer_raw=31 expected=188 decoded=false native_prefix=true stopped="DCTDecode"
```

The visible text path was already safe, while the direct renderer review truncated `raw_length` to `31` instead of the full ASCII85 DCT payload.

## Implementation

`PdfImageRenderer::dctPrefixFirstFilterHasBoundedEndBeforeTerminator()` now recognizes ASCII85 prefixes. When a candidate `endstream` follows an early ASCII85 EOD that decodes to an incomplete JPEG, the renderer can scan later `<~ ... ~>` members in the same candidate payload and accept the later terminator only when that member decodes to a complete DCT/JPEG preview boundary. DCT raster bytes remain review-only and are not decoded natively.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
...
PASS keeps ASCII85 DCTDecode early EOD decoys inside renderer image payload boundaries
...
1 test files, 566 assertions, 0 failures
```

This adds 1 focused PASS case and raises the focused DCT boundary file from 523 to 566 assertions.

```text
php lanes/markerpdf/examples/wordpress-pdf-dctdecode-ascii85-prefix-boundary-currentbase.php
```

The smoke emitted `ascii85_member_eod_ignored_until_dct_boundary=true`, `direct_renderer_payload_repaired_to_later_jpeg_boundary=true`, `stale_owner_payload_excluded_from_visible_text=true`, `stale_owner_payload_excluded_from_review=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat accepted direct DCT stream exclusion, DCT APP/SOS marker parsing, LZW-prefixed DCT, RunLength-prefixed DCT, ASCIIHex-prefixed DCT text/image review, Flate-prefixed DCT, malformed filter operands, CMYK/YCCK color transform planning, inline DCT boundaries, CCITTFax/JPX/JBIG2 preview-only filters, OCR/model execution, or supplied-boundary table/equation handoffs.

The bounded behavior is specifically the direct image-renderer review path for ASCII85-prefixed DCTDecode streams with an early ASCII85 member EOD and stale owner terminator before a later complete JPEG member.

## Dependency Closure

No new support component is needed. This reuses the native PDF stream dictionary parser, image filter stack resolution, ASCII85 decoder, DCT/JPEG marker boundary checker, `PdfImageRenderer`, `PdfTextExtractor`, and WordPress smoke path. Full live OCR/model/raster parity remains intentionally out of scope under the current no-GPU markerPDF directive.
