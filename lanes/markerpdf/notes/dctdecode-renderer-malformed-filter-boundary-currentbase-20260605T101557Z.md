# DCTDecode Renderer Malformed Filter Boundary Current Base

Slice: `markerpdf-dctdecode-filter-boundary-current-base-20260605T101557Z`
Base: `a63d0e111d11d4cbd43704afd1c4614546f1110e`

## Source truth

Upstream markerPDF delegates raster image rendering to native image/rendering dependencies while searchable text import keeps image payload bytes out of document text. Under the current no-GPU/no-model markerPDF scope, the PHP port should preserve native parser boundaries and review metadata without claiming full raster decode. DCT/JPEG image streams are therefore treated as review-only when the parser can prove raw JPEG SOI/EOI ownership, and malformed filter operands remain fail-closed unsupported filters.

## Implemented behavior

`PdfImageRenderer` now lets image XObject streams with malformed or unresolved image filter operands use a visible raw JPEG SOI/EOI boundary as `raw_dct_preview_boundary` metadata for direct renderer review rows. This keeps ICCBased image preview rows review-only instead of throwing before review metadata can be returned.

The malformed filter remains visible as unsupported metadata:

- `filters`: `["MalformedFilterOperand"]`
- `unsupported_filters`: `["MalformedFilterOperand"]`
- `decode_failed`: `true`
- `decoded_with_current_filters`: `false`
- `preview_only_filters`: `[]`
- `raw_dct_preview_boundary`: `true`

The WordPress smoke now covers both searchable text extraction and the direct renderer path, proving fake `endstream`/object bytes embedded before JPEG EOI do not leak into paragraphs and do not become native raster decode claims.

## Red-first evidence

After adding the focused renderer case and before the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 338 assertions, 1 failures`

Failure: `InvalidArgumentException: ICCBased image stream filters must be natively decoded before RGB preview.`

## Verification

After the source fix:

`php tools/run-tests.php lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`

Result: `1 test files, 352 assertions, 0 failures`

Adjacent malformed-filter/image regression:

`php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php`

Result: `2 test files, 541 assertions, 0 failures`

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-dctdecode-malformed-filter-boundary-currentbase.php`

Result: passed and emitted `renderer_raw_dct_preview_boundary=true`, `malformed_filter_operand_fail_closed=true`, `embedded_fake_object_rejected=true`, `dctdecode_image_payload_excluded_from_text=true`, and both model/external-tool execution flags false.

Syntax and patch hygiene:

`php -l lanes/markerpdf/src/PdfImageRenderer.php`

Result: no syntax errors detected.

`php -l lanes/markerpdf/tests/PdfDctDecodeFilterBoundaryCurrentBaseTest.php`

Result: no syntax errors detected.

`php -l lanes/markerpdf/examples/wordpress-pdf-dctdecode-malformed-filter-boundary-currentbase.php`

Result: no syntax errors detected.

`php -r 'json_decode(file_get_contents("lanes/markerpdf/lane-status.json"), true, 512, JSON_THROW_ON_ERROR); echo "lane-status json ok\n";'`

Result: `lane-status json ok`

`git diff --check -- lanes/markerpdf`

Result: clean.

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted direct DCT SOI/EOI stream recovery, direct renderer resolved DCT boundary handling, DCT APP segment scanning, Flate/ASCIIHex/null/trailing-null/indirect/unsupported-prefix/Crypt Identity boundaries, malformed text-extractor filter operands, inline DCT tokenizer behavior, CCITT/JPX/JBIG2 image boundaries, or inline malformed-filter preview fail-closed behavior. The owned surface is the direct `PdfImageRenderer` ICCBased review row when the image XObject has a malformed DCTDecode filter operand but a provable raw JPEG stream boundary.

## Dependency closure

No new support component is needed. The patch reuses the existing native PDF object parser, image stream filter metadata, JPEG marker scanner, and `PdfImageRenderer` review pipeline. Full JPEG rasterization through PDFium/PIL, OCR, Surya/Texify/Torch/model execution, and exact upstream model benchmark parity remain intentionally out of scope for this no-GPU markerPDF slice.
