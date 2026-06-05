# Inline Image Decode Boundary Current Base - 2026-06-05 19:49 UTC

Lane: markerpdf
Micro-slice: markerpdf-inline-image-decode-boundary-current-base-20260605T194930Z
Accepted base: 5edce53ce25e0357fcc261e6cb4a7769601eab7f

## Source Truth

Upstream markerPDF routes searchable PDF conversion through parser-backed text extraction before any OCR/model fallback. Under the current no-GPU scope, inline `BI ... ID ... EI` raster payloads are treated as native PDF parser boundaries: bytes inside complete image payloads must not become WordPress paragraph text, and fake delimiter-looking `EI` tokens inside image-owned surplus must remain closed until the real inline-image terminator.

This slice maps the native/PDF parser behavior for a native-filter-wrapped preview image: `/F [/Fl /JPXDecode]` can omit `/CS` and `/BPC` because the preview codestream carries image framing. Once Flate reaches a complete stream end and the decoded JPX codestream is complete, surplus bytes containing a fake `EI` stay image-owned and the following text object remains extractable.

## Behavior Added

- `PdfTextExtractor` now recognizes complete preview-filter inline image payloads after native-prefix decoding for Flate+JPX, DCT, and CCITT preview filters.
- The completion check is deliberately fail-closed: it requires a completed first native filter, surplus containing a fake `EI` after that native filter, and complete preview-filter framing after the native-prefix decode.
- Unknown preview filters, unresolved DecodeParms, and malformed native prefixes continue to use the existing conservative inline-image fallbacks.

## Evidence

- Red-first focused run before the source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  Result: `1 test files, 487 assertions, 1 failure`.
- Focused run after the source fix:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  Result: `1 test files, 495 assertions, 0 failures`.
- Adjacent inline-image/image-filter gate:
  `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxSmaskDecodeCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineJpxColorKeyOutputPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamJpxCMapRepairCurrentBaseTest.php lanes/markerpdf/tests/PdfParserInlineStreamOwnerCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`
  Result: `11 test files, 1578 assertions, 0 failures`.
- WordPress smoke:
  `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  Result: emits `flate_wrapped_jpx_no_sample_floor_payload_excluded_until_real_ei=true`, `flate_wrapped_jpx_no_colorspace_or_bpc_sample_floor=true`, `flate_wrapped_jpx_decoded_preview_framing_complete=true`, `visible_text_imported=true`, `excluded_inline_image_text=true`, and all Python/model/external-tool execution flags false.

A wider exploratory run that also included `PdfTextExtractorTest.php` still reports two unrelated ToUnicode UseCMap failures outside this inline-image slice; the bounded inline-image gate above is green.

## Non-Overlap

This does not repeat the earlier accepted inline-image sample-floor, ASCIIHex surplus, stacked native-filter, DCT/JBIG2/CCITT preview fallback, or wrapped ASCIIHex+JPX supplied-sample behavior. The new case is specifically Flate-wrapped JPX preview completion without decoded sample-floor metadata.

## Dependency Closure

No new support component is needed. The slice reuses existing native PHP stream filter decoding, DecodeParms resolution, and preview-filter framing helpers. GPU/model OCR, raster execution, external PDF tools, PDFium, Surya, Texify, Torch, and live-service workers remain intentionally out of scope.
