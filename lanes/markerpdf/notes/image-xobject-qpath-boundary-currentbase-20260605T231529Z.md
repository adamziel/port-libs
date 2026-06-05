# markerPDF Image XObject q/Q Current-Path Boundary Current Base

Session: `port-dev-markerpdf-image-xobject-20260605T231529Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260605T231529Z`
Base accepted HEAD: `0e5ef6de4af738adb4c175e82b284d04992b9f2e`

## Source Truth

Upstream markerPDF keeps searchable text extraction separate from image rendering/review. Rendered page images and raster media are handled after parser/PDF content interpretation, while searchable text stays in the text pipeline. Under the current no-GPU markerPDF scope, this native PHP slice maps the parser boundary needed before future raster/media handoff.

PDF `q` / `Q` saves and restores the graphics state, including CTM and clipping path, but the current path itself is not saved in that graphics-state stack. The current path remains live until it is explicitly consumed or cleared by path-painting operators or `n`. Image XObject review must therefore preserve current-path lifetime across `Q` before deciding the active clipping boundary at a later `W` / `W*`.

## Behavior

`PdfTextExtractor` now keeps current-path state live across graphics-state restores:

- Image XObject invocation review restores saved CTM, clipping, color, ExtGState, and marked-content state on `Q`, but preserves the live path bbox/current point/start point.
- Pattern image paint review uses the same current-path-preserving restore helper.
- Text/clipping scanners restore only saved clip and CTM on `Q`; they no longer revive a path cleared inside a nested graphics state.

The focused fixture covers two boundaries in one content stream:

- `q q ... path ... Q W n ... /QPath Image Do Q` proves a path constructed inside `q` survives the inner `Q` and clips the Image XObject to `[10,10,40,30]`.
- `path q W n Q W n ... /Cleared QPath Image Do` proves a path consumed and cleared inside `q` is not resurrected by `Q`; the later image remains unclipped at `[60,0,110,40]`.

Both Image XObject payload streams stay out of visible WordPress text and out of serialized review JSON.

## Red First

Before the source change, an in-memory `php -r` probe for the first boundary returned no clip bbox and a full visible image bbox:

```text
array (
  0 => array (),
  1 => array (0 => 0.0, 1 => 0.0, 2 => 50.0, 3 => 40.0),
  2 => false,
)
```

After the first image-review source edit, the formal fixture exposed the same stale-path restore bug in text clipping: the second boundary clipped away the following text line. The final patch keeps scalar clipping current paths live across `Q` as well.

## Verification

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 1072 assertions, 0 failures
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfPageArtifactMarkedContentClipCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
1 test files, 10 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-qpath-currentbase.php
```

The smoke exits 0 and emits `image_xobject_count=2`, `invoked_image_xobject_count=2`, `q_path_image_visible_bbox=[10,10,40,30]`, `q_path_clip_applied=true`, `cleared_q_path_image_visible_bbox=[60,0,110,40]`, `cleared_q_path_clip_applied=false`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
php -l lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-qpath-currentbase.php
```

All syntax checks reported no syntax errors.

Root harness status: not run - isolated micro-slice.

## Status Delta

- Focused TestRunner pass count: `2267 -> 2268`.
- WordPress smoke scenarios: `1949 -> 1950`.
- Focused image XObject boundary suite: `1056` assertions before the added fixture, then `1072` assertions green.
- Manifest row `pdfImageXObjectPlacementBoundaryCurrentBaseBehaviors`: `2 -> 3`, mapped count `2 -> 3`.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, CTM-only placement, Form XObject inheritance, Contents array graphics-state preservation, rectangular/path clipping basics, compound clipping intersection normalization, page-box clipping, rotation/UserUnit display geometry, optional content, artifacts, marked-content metadata, ExtGState transparency, masks/SMask, Decode, named ColorSpace, JPX `SMaskInData`, image masks, tiling/stroking patterns, Type3 CharProc image review, malformed `Do`, malformed `cm`, compatibility/text-object exclusion, resource-generation selection, or encrypted fail-closed review.

The bounded behavior is only PDF current-path lifetime across `q` / `Q` before Image XObject clipping and WordPress media-review handoff.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, content tokenizer, graphics-state stacks, matrix math, clipping-path tracker, stream decoder, Image XObject review rows, and WordPress smoke renderer. Full pixel/raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend; live OCR, Surya/Texify/Torch, model workers, external PDF tools, and exact upstream model benchmark parity remain intentionally out of scope under the current no-GPU markerPDF direction.
