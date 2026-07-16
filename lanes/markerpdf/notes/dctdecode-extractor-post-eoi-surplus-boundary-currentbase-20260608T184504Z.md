# DCTDecode Extractor Post-EOI Surplus Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T184504Z`
Base accepted HEAD: `307a601051e9f25717d7e310792b824a3d11215f`

## Source Truth

Upstream markerPDF keeps searchable PDF text extraction separate from image rendering. JPEG/DCTDecode payload bytes are image payload review data, not visible document text. This port already clipped DCTDecode Image XObject review bytes at the first complete JPEG EOI boundary and the renderer already recorded post-EOI surplus provenance for review-only image streams.

This slice ports the same bounded post-EOI provenance to the primary `PdfTextExtractor` Image XObject review boundary: `dctdecode_stream_boundary` now records the declared raw stream length and the clipped post-JPEG-EOI surplus byte count, SHA-256, and preview hex while `entry.raw_length` remains clipped to the JPEG payload.

## Evidence

- Red-first: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePostEoiSurplusExtractorBoundaryCurrentBaseTest.php` failed with `Expected: 64`, `Actual: NULL` after 19 assertions because extractor `dctdecode_stream_boundary` had no post-EOI surplus metadata.
- Fixed focused run: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodePostEoiSurplusExtractorBoundaryCurrentBaseTest.php` => `1 test files, 28 assertions, 0 failures`.
- Adjacent DCT boundary run: `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRendererPostEoiSurplusBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodePostEoiSurplusExtractorBoundaryCurrentBaseTest.php` => `3 test files, 763 assertions, 0 failures`.
- WordPress smoke: `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-post-eoi-boundary-currentbase.php` exits 0 and emits `xobject_post_eoi_surplus_recorded=true`, `xobject_post_eoi_surplus_byte_count=60`, `post_eoi_surplus_excluded_from_text=true`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

## Non-Overlap

This does not repeat renderer-only post-EOI surplus recording, direct DCTDecode clipping, native-prefix DCT boundaries, soft-mask/mask/alternate image review, inline image handling, post-DCT filter review, or model/OCR/raster execution. It is extractor-side primary Image XObject boundary metadata only.

## Dependency Closure

No new support component is required. The patch reuses the native PHP PDF parser, Image XObject review path, and existing DCT JPEG marker scanner. GPU/model/OCR behavior remains intentionally out of scope for this no-GPU markerPDF lane.
