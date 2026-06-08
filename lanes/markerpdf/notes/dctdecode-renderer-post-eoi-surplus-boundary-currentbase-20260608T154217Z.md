# DCTDecode Renderer Post-EOI Surplus Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260608T154217Z`

Base: `b74dfb666585975f95b4cdb08212431ed64ad41f`

## Behavior

Upstream markerPDF treats DCTDecode image bytes as image payload for review/conversion, not searchable page text. Existing native PHP coverage already clipped direct DCTDecode post-EOI surplus from review bytes before text/media handoff. This slice keeps that accepted clipped payload behavior and adds renderer provenance for the original declared direct-DCT stream when non-padding bytes follow the final JPEG EOI before a valid `endstream`.

Before the patch, a direct DCTDecode ICCBased renderer preview clipped `image_stream.raw_length` to the JPEG length, but `dctdecode_stream_boundary` only saw the clipped bytes, so it reported `raw_stream_length == review_stream_length` and `stream_trimmed_to_jpeg_eoi=false`. The renderer now records metadata-only declared payload provenance when the declared stream starts with the already-safe clipped JPEG review stream:

- `raw_stream_length` remains the declared DCT stream payload length for the boundary.
- `review_stream_length` remains the clipped JPEG payload length.
- `stream_trimmed_to_jpeg_eoi=true`.
- `post_jpeg_eoi_surplus_byte_count`, `post_jpeg_eoi_surplus_sha256`, and a bounded hex preview describe the surplus without exposing it as extracted text.

The direct image stream bytes used by review remain clipped to JPEG EOI. Prefix-encoded DCT streams and existing fake-`endstream` marker-boundary behavior are unchanged.

## Verification

Red-first probe before the source patch:

- `php -r 'require "tools/bootstrap.php"; use PortLibs\MarkerPDF\PdfImageRenderer; ...'`
- Result: image stream payload was clipped, but `dctdecode_stream_boundary` reported `raw_stream_length=25`, `review_stream_length=25`, and `stream_trimmed_to_jpeg_eoi=false`.

Focused tests after the patch:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeRendererPostEoiSurplusBoundaryCurrentBaseTest.php`
- Result: `1 test files, 29 assertions, 0 failures`

Adjacent DCT boundary family:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeRendererPostEoiSurplusBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeRendererStreamBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`
- Result: `3 test files, 772 assertions, 0 failures`

Example smoke:

- `php lanes/markerpdf/examples/wordpress-pdf-dctdecode-renderer-post-eoi-surplus-currentbase.php`
- Result: exits 0; emitted metadata had `image_stream_raw_length=89`, `declared_stream_length=160`, `review_stream_length=89`, `stream_trimmed_to_jpeg_eoi=true`, `post_jpeg_eoi_surplus_byte_count=71`, `dctdecode_image_payload_excluded_from_text=true`, `executes_python_or_models=false`, `executes_external_pdf_tools=false`, and `executes_pypdfium_or_pil=false`.

Syntax/format checks:

- `php -l lanes/markerpdf/src/PdfImageRenderer.php` => no syntax errors
- `php -l lanes/markerpdf/tests/PdfDctDecodeRendererPostEoiSurplusBoundaryCurrentBaseTest.php` => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-renderer-post-eoi-surplus-currentbase.php` => no syntax errors
- `php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'` => `lane-status json ok`

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted direct DCTDecode post-EOI clipping slice: that behavior still governs the review bytes and extractor handoff. This patch only adds renderer boundary provenance for declared post-EOI surplus after clipping. It also does not alter prefix-filter DCTDecode behavior, inline image DCTDecode padded-SOI behavior, DCT DecodeParms ownership, malformed DCT filter operands, fake `endstream` tokens inside JPEG scan data, or filters declared after DCTDecode.

## Dependency Closure

No new support component is needed. The implementation reuses the existing native PHP PDF dictionary scanner, stream terminator guard, DCT/JPEG marker scanner, and renderer metadata path. No Python, CUDA, OCR, model execution, PDFium/PIL raster decode, JavaScript/action execution, network service, or external PDF tool is required.

## Next

Continue with non-overlapping native markerPDF parser/converter work: xref repair, object-stream filter metadata, font/CMap width fidelity, annotations/forms/security preflight, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
