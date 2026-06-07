# Image XObject BBox Operand Tail Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260607T054245Z`
Base accepted HEAD: `061d9508a12b92da2c019cd6c353e28f42245284`

## Upstream Boundary

`marker.pdf.extract_text` should preserve searchable page text while image
handoffs stay review-only unless a native raster backend owns rendering. For
Form XObject and tiling Pattern image review, `/BBox` is a clipping boundary
for nested image placement. If the dictionary value has an extra top-level
operand before the next key, such as `/BBox [0 0 1 1] 99 /Resources ...`, the
rectangle is malformed and is ignored instead of clipping or excluding a nested
image with an ambiguous operand sequence.

## Implementation

- Added focused Form XObject coverage where a malformed `/BBox` operand tail is
  ignored while a sibling valid Form `/BBox` still clips the nested image.
- Added focused tiling Pattern coverage where a malformed `/BBox` operand tail
  is ignored while a sibling valid Pattern `/BBox` still excludes an image that
  falls outside the tile bbox.
- Reused `topLevelPdfNameHasTrailingTopLevelOperand()` before
  `pdfRectangleValueAfterName()` accepts a rectangle.
- Tightened that trailing-operand helper so bare dictionary bodies are scanned
  as-is instead of accidentally starting at the first nested dictionary.
- Added a WordPress smoke covering both malformed and valid Form/Pattern BBox
  paths without exposing image payload bytes in visible text.

## Evidence

Red-first focused run before source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBBoxOperandTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
FAIL ignores malformed Form XObject BBox arrays with trailing operands before image clipping
FAIL ignores malformed tiling Pattern BBox arrays with trailing operands before image clipping
1 test files, 20 assertions, 2 failures
```

Focused run after source edit:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectBBoxOperandTailBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PASS ignores malformed Form XObject BBox arrays with trailing operands before image clipping
PASS ignores malformed tiling Pattern BBox arrays with trailing operands before image clipping
1 test files, 81 assertions, 0 failures
```

WordPress smoke:

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-bbox-operand-tail-currentbase.php
```

The smoke exits 0 and emits
`malformed_form_bbox_tail_ignored=true`, `valid_form_bbox_clips=true`,
`malformed_pattern_bbox_tail_ignored=true`,
`valid_pattern_bbox_excludes=true`, `payload_in_visible_text=false`,
`executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted Image XObject payload exclusion, `Do` operand
arity, `cm` operand arity, optional-content visibility, artifact suppression,
path/page clipping, compound clip intersections, zero-area CTM suppression,
zero-alpha ExtGState suppression, Form/Pattern/Type3 traversal, direct resource
tail rejection, indirect BBox operand resolution, `/SMask /None`, masks,
metadata, OPI, filter metadata, inline-image tokenizer behavior, or live raster
execution. The bounded behavior is only malformed top-level operands after
Form/Pattern `/BBox` rectangle values before image review clipping.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP
content-stream tokenizer, dictionary operand scanner, graphics-state matrix and
clipping helpers, Image XObject review collector, stream decoders, focused PHP
tests, and WordPress smoke path. Full rendered-image parity remains gated on a
future native raster backend; live OCR, Surya/Texify/Torch, GPU/model workers,
external PDF tools, and exact upstream model benchmark parity were not run.
