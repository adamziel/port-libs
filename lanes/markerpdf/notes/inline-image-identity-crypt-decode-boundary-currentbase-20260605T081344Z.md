# Inline Image Identity Crypt Decode Boundary Current Base

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260605T081344Z`
Base: `eabf2addac7c2c5b012c94b74de9b49f75b6cfef`

## Source Truth

Upstream markerPDF delegates searchable-PDF text extraction to PDF parser text extraction and does not invoke OCR/model workers for native inline image delimiters. For PDF stream filtering, `/Filter /Crypt` is only natively pass-through when its decode parameters identify `/Name /Identity`; named crypt filters require the document security handler and must remain fail-closed in this no-GPU/native-parser lane.

## Behavior

- `PdfTextExtractor` now evaluates inline image filter support with aligned `/DecodeParms`.
- Inline image `/Filter /Crypt /DecodeParms << /Name /Identity >>` is treated as a verifiable pass-through filter, so delimiter-looking `EI` bytes remain image payload until the declared RGB sample floor is reached.
- Non-identity or missing-parameter `/Crypt` remains an unsupported inline-image filter and is still review-only.
- `PdfImageRenderer` mirrors that boundary for preview rows: identity Crypt bytes are decoded as pass-through before RGB sample preview, while unsupported Crypt remains rejected for native preview.

## Evidence

Red-first before implementation:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
FAIL treats identity Crypt inline image filters as pass-through before RGB preview
Inline image filters must be natively decoded before output preview.
```

Focused verification after implementation:

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php

php -l lanes/markerpdf/src/PdfImageRenderer.php
No syntax errors detected in lanes/markerpdf/src/PdfImageRenderer.php

php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php

php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php

php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
1 test files, 264 assertions, 0 failures

php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageTokenizerBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageMalformedFilterPreviewCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDctDecodeFilterBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfParserObjectStreamInlineImageFilterCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
6 test files, 1011 assertions, 0 failures

php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
exits 0 and reports identity_crypt_inline_filter_payload_excluded_until_sample_floor=true,
identity_crypt_inline_filter_native_decode=true, identity_crypt_inline_filter_preview_pixels=2,
and excluded_inline_image_text=true.

git diff --check -- lanes/markerpdf
passes with no output.
```

Focused assertion delta: `PdfInlineImageDecodeBoundaryCurrentBaseTest.php` increased from `230` to `264` assertions in the red-first slice, adding 1 focused PASS case.

## Non-Overlap

This does not repeat the existing unsupported `/Crypt` review-only boundary, DCT/JPX/JBIG2/CCITT preview-only filters, CMap identity Crypt stream behavior, encrypted-permission crypt-filter preflight, or prior Flate/LZW/RunLength/ASCIIHex/ASCII85 inline decode boundaries.

## Dependency Closure

No new support component is needed. The patch reuses the existing native inline image tokenizer, stream filter decoder, `/DecodeParms` alignment, and image preview metadata paths. OCR, Surya, Texify, Torch, PDFium execution, and model workers remain intentionally out of scope.
