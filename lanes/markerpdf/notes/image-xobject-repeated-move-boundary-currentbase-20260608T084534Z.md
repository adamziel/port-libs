# markerPDF Image XObject Repeated Moveto Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260608T084534Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T084534Z`
Base accepted HEAD: `efe757fea34410e917212cb2f88d964760b187d1`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image rendering/review. Rendered page images and raster media are handled after parser/PDF content interpretation, while searchable text stays in the text pipeline. Under the current no-GPU markerPDF scope, this native PHP slice maps the parser boundary needed before future raster/media handoff.

The PDF path construction boundary is that a dangling `m` followed immediately by another `m` replaces the prior move point. That stale point must not enlarge a later `W` / `W*` clipping path used for Image XObject review. Real prior subpaths still remain part of the current path bbox.

References:

- Upstream markerPDF commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34`, `marker/pdf/images.py` and image extraction flow.
- Adobe PDF Reference / PDF path construction semantics: https://opensource.adobe.com/dc-acrobat-sdk-docs/pdfstandards/pdfreference1.3.pdf

## Behavior

`PdfTextExtractor::applyClipPathStateOperator()` now detects when the current path consists only of an unpainted move point and replaces that point on the next `m` instead of unioning it into the path bbox.

The focused fixture covers:

- `10 10 m 100 100 m ... W n ... /Repeated Move Image Do` clips the image to `[100,100,120,120]` instead of stale `[10,10,120,120]`.
- A control with a completed first subpath followed by a second `m` still keeps the combined bbox `[10,10,120,120]`.

Both Image XObject payload streams remain out of visible WordPress text and out of serialized review JSON.

## Red First

Before the source fix, the focused command failed with the stale dangling move point included in the clip bbox:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectRepeatedMovePathBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL replaces dangling repeated moveto before image XObject clipping boundaries (lanes/markerpdf/tests/PdfImageXObjectRepeatedMovePathBoundaryCurrentBaseTest.php)
Expected: [[100.0,100.0,120.0,120.0]]
Actual: [[10.0,10.0,120.0,120.0]]
1 test files, 13 assertions, 1 failures
```

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectRepeatedMovePathBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS replaces dangling repeated moveto before image XObject clipping boundaries
1 test files, 44 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObject*Test.php lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php
Focused test run: 37 selected test files (root lock skipped)
37 test files, 2710 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-repeated-move-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=2`, `invoked_image_xobject_count=2`, `repeated_move_clip_bbox=[100,100,120,120]`, `repeated_move_visible_bbox=[100,100,120,120]`, `multi_subpath_clip_bbox=[10,10,120,120]`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `2999 -> 3000`.
- WordPress smoke scenarios: `2484 -> 2485`.
- New focused test: `PdfImageXObjectRepeatedMovePathBoundaryCurrentBaseTest.php`, 1 PASS case / 44 assertions.
- Manifest row `pdfImageXObjectPlacementBoundaryCurrentBaseBehaviors`: `2 -> 3`, mapped count `2 -> 3`.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM-only placement, Form XObject inheritance, Contents array graphics-state preservation, rectangular/path clipping basics, compound clipping intersection normalization, page-box clipping, rotation/UserUnit display geometry, optional content, artifacts, marked-content metadata, ExtGState transparency, masks/SMask, Decode, named ColorSpace, JPX `SMaskInData`, image masks, tiling/stroking patterns, Type3 CharProc image review, malformed `Do`, malformed `cm`, malformed path operands, compatibility/text-object exclusion, resource-generation selection, q/Q current-path preservation, or encrypted fail-closed review.

The bounded behavior is only repeated dangling `m` path construction before Image XObject clipping and WordPress media-review handoff.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, content tokenizer, matrix math, clipping-path tracker, stream decoder, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
