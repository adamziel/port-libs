# markerPDF Image XObject SMask Matte Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260606T003216Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260606T003216Z`
Base accepted HEAD: `a0f9a4e8486a1870b3b58c910a9dc3a6b97dbb35`

## Source Truth

Upstream markerPDF separates searchable PDF text extraction from image rendering. Under the current no-GPU scope, native PHP maps the parser-side metadata boundary before any future PDFium/PIL-style RGB image handoff. PDF image soft masks may include `/Matte` components for preblended image data; those components need review metadata before RGB unblending while the soft-mask stream payload remains outside visible text.

## Behavior

`PdfTextExtractor::extractImageXObjectBoundaryReview()` now records soft-mask `/Matte` metadata on Image XObject `soft_mask_review` rows:

- `matte` preserves normalized component values.
- `matte_review` records component count, expected parent image ColorSpace components, match state, source, and review-only status.
- `matte_unblending_required` is true only when the matte component count matches the parent image components.

The focused fixture covers an RGB Image XObject with a matching three-component SMask `/Matte [0.25 0.5 0.75]`, plus a CMYK Image XObject whose three-component matte is flagged as mismatched. Image and alpha stream payload bytes stay out of WordPress paragraph text and serialized review JSON.

## Red First

Before the source change, a focused probe for an RGB Image XObject with an external grayscale SMask and `/Matte [0.25 0.5 0.75]` returned a `soft_mask_review` row with decode/filter/hash metadata but no matte fields.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1092 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-smask-matte-currentbase.php
```

The smoke exits 0 and emits `matte_components=[0.25,0.5,0.75]`, `matte_matches_image_components=true`, `matte_unblending_required=true`, `mismatch_expected_components=4`, `mismatch_matches_image_components=false`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-smask-matte-currentbase.php
```

All syntax checks reported no syntax errors.

```text
jq empty lanes/markerpdf/lane-status.json lanes/markerpdf/UPSTREAM_TEST_MANIFEST.json
git diff --check -- lanes/markerpdf
```

Both commands exited 0.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `2297 -> 2298`.
- WordPress smoke scenarios: `1973 -> 1974`.
- Focused Image XObject boundary suite: `1072 -> 1092` assertions.
- Manifest row `pdfImageXObjectSoftMaskMatteCurrentBaseBehaviors`: `0 -> 1`, mapped count `0 -> 1`.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM placement, Form XObject inheritance, optional content, page clipping, current-path q/Q behavior, ExtGState transparency, SMask stream generation selection, SMask `/None`, JPX `SMaskInData`, ColorKey masks, image `/Decode`, named ColorSpace, ImageMask paint color, tiling/stroking patterns, Type3 CharProc image review, malformed `Do`, or encrypted fail-closed review.

The bounded behavior is only external soft-mask `/Matte` component review before RGB media handoff.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, stream dictionary parser, indirect numeric array resolution, Image XObject review rows, stream decoders, and WordPress smoke path. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
