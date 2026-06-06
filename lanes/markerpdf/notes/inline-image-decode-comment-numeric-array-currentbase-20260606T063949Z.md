# Inline Image Decode Comment Numeric Array Boundary - 2026-06-06

Micro-slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T063949Z`
Base: `8b9a89d4d40dfee6bec490587ed2daf5b7734133`

## Source Truth

Upstream `sddai/markerPDF` separates searchable PDF text extraction from image rendering and metadata review. In the native no-GPU PHP port, inline image payloads and image review metadata must not leak into WordPress paragraphs, but valid PDF image dictionaries should still preserve `/Decode` metadata before RGB or ImageMask preview handoff.

PDF comments are whitespace. A `/Decode` array such as:

```text
/D [0 3 % decoy 0 1 0 1
]
```

contains only the numeric range `[0 3]`; numbers in the comment are not decode components. Literal and hex-string decoys inside a numeric array remain invalid operands and should fail closed.

## Red-First Evidence

Before the source edit, this probe counted the comment numbers as real `/Decode` values:

```text
/W 1 /H 1 /CS [/I /RGB 3 91 0 R] /BPC 8 /D [0 3 % comment 0 1 0 1
]
```

Observed before the patch:

```text
source=explicit count=3 valid=0 review=1
```

After the patch, the same probe returns:

```text
source=explicit count=1 valid=1 review=0
```

Literal and hex-string decoys now report `component_count=0`, `valid_for_components=false`, and review-only metadata instead of being silently counted.

## Implementation

- `PdfImageRenderer::numericArrayValue()` now parses bracketed PDF numeric arrays through the existing token-aware array walker.
- Comments are skipped by the PDF whitespace path and therefore do not contribute numeric operands.
- Actual non-numeric tokens in numeric arrays return an empty value list so invalid `/Decode` operands remain fail-closed.
- Existing non-bracket fallback behavior is preserved for legacy scalar callers.

## Verification

- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - `1 test files, 730 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageColorSpaceMaskInlineOutputPreviewCurrentBaseTest.php`
  - `5 test files, 1400 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php > /tmp/markerpdf-inline-image-decode-boundary-currentbase.html && php <<'PHP' ...`
  - `visible_text_imported=true`
  - `comment_inline_decode_preview_accepted=true`
  - `comment_inline_mask_decode_preview_accepted=true`
  - `literal_inline_decode_decoy_fails_closed=true`
  - `hex_inline_decode_decoy_fails_closed=true`
  - `excluded_inline_image_text=true`
  - `executes_python_or_models=false`
  - `executes_external_pdf_tools=false`
- `php -l lanes/markerpdf/src/PdfImageRenderer.php`
  - no syntax errors
- `php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php`
  - no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php`
  - no syntax errors
- `php -r '$path="lanes/markerpdf/lane-status.json"; json_decode(file_get_contents($path), true); if (json_last_error() !== JSON_ERROR_NONE) { fwrite(STDERR, json_last_error_msg().PHP_EOL); exit(1); } echo "valid json\n";'`
  - `valid json`
- `git diff --check -- lanes/markerpdf`
  - no whitespace errors

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted inline image ASCII85/ASCIIHex/LZW/RunLength/Flate EOD boundaries, DCT/JPX/JBIG2/CCITT preview-only tokenizer framing, null-filter alignment, unsupported filter review metadata, duplicate `/Decode` detection, malformed or unresolved top-level `/Decode` operands, indirect inline geometry/decode operands, Identity Crypt boundaries, or wrapped terminal native-filter surplus handling.

The bounded behavior is specifically token-aware numeric `/Decode` array parsing where PDF comments are whitespace and non-comment non-numeric tokens fail closed.

## Dependency Closure

No new support component is needed. The slice reuses the native PHP dictionary/value parser, balanced array token reader, image Decode planner, inline image renderer preview paths, and existing WordPress smoke. GPU/OCR/model execution, pypdfium/PIL raster parity, Surya/Torch, Texify, Streamlit/FastAPI workers, external PDF tools, and online services remain intentionally out of scope.

## Next Task

Continue native no-GPU markerPDF work on a non-overlapping parser/import-fidelity gap: fonts, CMaps, stream filters, xref repair, metadata, outlines, annotations, forms, page geometry, image/filter metadata, or supplied-boundary table/equation handoffs.
