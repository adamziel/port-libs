# DCTDecode Renderer Stream Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T090112Z`

Base accepted HEAD: `f0deb377edb0acf5cab465b164fb1c6dc0b4c874`

## Source Truth

Upstream markerPDF hands DCT/JPEG image payloads to image rendering/review paths rather than treating the JPEG byte stream as searchable PDF text. In the no-GPU PHP port, DCTDecode remains preview-only, but image/filter metadata must preserve the real JPEG payload boundary so WordPress review can report accurate raw stream metadata without leaking fake PDF objects embedded inside JPEG bytes.

## Red-First Gap

`PdfTextExtractor` already had DCT-aware object-stream boundary recovery, but the direct `PdfImageRenderer::iccBasedImageStreamPreviewRows()` path still reached `streamPayloadBytes()` through a generic non-greedy `endstream` regex. A direct renderer probe with a DCT image stream containing `endstream/endobj/9 0 obj/...` inside the JPEG payload reported `image_stream.raw_length=19` instead of the full `124` byte JPEG payload before this patch.

## Implementation

- `PdfImageRenderer::streamPayloadBytes()` now accepts the current object map and first tries a DCT-aware stream-boundary path before the generic regex fallback.
- Raw DCT streams scan from JPEG SOI to the final complete EOI whose padded boundary is followed by the real stream terminator.
- Prefix-decoded DCT streams test candidate terminators by decoding supported native prefix filters before DCT and requiring a complete JPEG payload before accepting the terminator.
- Non-DCT streams continue to use the existing regex fallback.

## Focused Evidence

- Baseline focused DCT family before this assertion was added: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php` => `3 test files, 329 assertions, 0 failures`.
- After patch: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeSegmentBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php` => `3 test files, 349 assertions, 0 failures`.
- Adjacent renderer check: `php tools/run-tests.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageIccSoftMaskDecodeTransparencyCurrentBaseTest.php` => `2 test files, 561 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-renderer-boundary-currentbase.php` emits `xobject_raw_length_recovered=true`, `renderer_raw_dct_length_recovered=true`, `renderer_flate_dct_length_recovered=true`, `dctdecode_image_payload_excluded_from_text=true`, and all model/external-tool flags false.

## Non-Overlap

This slice does not repeat the accepted searchable-text DCT stream recovery, DCT APP segment, inline DCT boundary, Flate/null-slot text boundary, ASCIIHex early-EOD, indirect filter, unsupported prefix, Crypt Identity, malformed-filter, or trailing-null-filter slices. It owns the direct image renderer stream metadata boundary shared by ICCBased preview/review rows.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP stream-filter decoders and JPEG marker scanning logic in `PdfImageRenderer`. GPU/OCR/model execution, pypdfium/PIL raster decoding, and external PDF tools remain intentionally out of scope for this no-GPU markerPDF lane.
