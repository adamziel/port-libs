# Inline Image Decode Generation Boundary

Slice: `markerpdf-inline-image-decode-boundary-current-base-20260606T223348Z`
Base: `d6cb1115b3a57bbc22a114ba70f49c1e4b8a243d`

## Source Truth

PDF indirect references carry both object number and generation. The native port already parses the generation in `pdfIndirectReferenceTokenAt()`, and xref repair work treats generation as part of the selected current object identity. This slice applies that same boundary to inline image `/Decode` operands before grayscale, Indexed, and ImageMask preview metadata.

Upstream markerPDF reaches these previews through PDF parser/PDFium object resolution and then image review/raster handoff. In the current no-GPU lane scope, this remains native parser and preview metadata behavior only; no pypdfium/PIL, OCR, Surya, Texify, Torch, Streamlit, FastAPI, or external PDF tools were launched.

## Red-First Probe

Before the source change, this probe selected stale generation-zero object `104 => [0 3]` for a nonzero-generation `/D 104 1 R` inline image decode:

```bash
php -r 'require "tools/bootstrap.php"; $r=new PortLibs\MarkerPDF\PdfImageRenderer(); $objects=[104=>"[0 3]","104:1"=>"[1 0]"]; $p=$r->inlineImageReviewPlan("/W 1 /H 1 /CS /G /BPC 8 /D 104 1 R", "\x80", $objects); var_export($p["image_decode"]);'
```

Observed before fix: `ranges[0] = [0.0, 3.0]`, `inverted_components = []`.

Observed after fix: `ranges[0] = [1.0, 0.0]`, `inverted_components = [0]`.

## Implementation

- `PdfImageRenderer::resolvePdfValueWithSeen()` now keys recursive object resolution by `object:generation`.
- Exact generation map keys are accepted as `104:1` or `104 1`.
- Integer object-map keys remain valid only for generation `0`, preserving existing current-base fixtures.
- Missing nonzero generations fail closed as unresolved operands instead of falling back to stale generation-zero bodies.

## Verification

```bash
php -l lanes/markerpdf/src/PdfImageRenderer.php
php -l lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

Result: all changed PHP files reported no syntax errors.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php
```

Result: `1 test files, 848 assertions, 0 failures`.

```bash
php tools/run-tests.php lanes/markerpdf/tests/PdfInlineImageDecodeBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfInlineImageDecodeArrayOperandCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineFilterPaletteAlphaCurrentBaseTest.php lanes/markerpdf/tests/PdfImageInlineMaskCurrentBaseTest.php lanes/markerpdf/tests/PdfImageRendererTest.php
```

Result: `5 test files, 1480 assertions, 0 failures`.

```bash
php lanes/markerpdf/examples/wordpress-pdf-inline-image-decode-boundary-currentbase.php
```

Smoke metadata checked:

- `visible_text_imported=true`
- `generation_exact_inline_decode_selected=true`
- `generation_zero_inline_decode_not_used_for_nonzero_ref=true`
- `generation_exact_inline_decode_payload_excluded=true`
- `excluded_inline_image_text=true`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

```bash
git diff --check -- lanes/markerpdf
```

Result: clean.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat the accepted duplicate `/Decode`, direct null `/Decode`, comment/literal/hex decoy Decode array, indirect generation-zero Decode array, ImageMask generation-zero geometry, inline filter EOD, or overlarge geometry boundary slices. It owns only the exact-generation object-resolution boundary for inline image `/Decode` operands.

## Dependency Closure

No new support component is needed. The slice reuses the existing native PDF token parser, inline image dictionary canonicalizer, object-map resolver, and preview metadata paths.
