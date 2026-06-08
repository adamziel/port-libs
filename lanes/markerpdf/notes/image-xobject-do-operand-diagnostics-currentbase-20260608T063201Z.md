# markerPDF Image XObject Do Operand Diagnostics

Session: `port-dev-markerpdf-image-xobject-20260608T063201Z`
Micro-slice: `markerpdf-image-xobject-boundary-current-base-20260608T063201Z`
Base accepted HEAD: `b472be70143c011b9bf5a67e62f8bebf49bd6f9c`

## Source Truth

Upstream markerPDF at pinned commit `da6a2f5c9a7b1e92c82d85fbcf3680a79dd28a34` keeps searchable text extraction separate from image rendering and image handoff:

- `marker/pdf/extract_text.py` owns searchable text extraction.
- `marker/pdf/images.py` owns page or bbox image rendering outside the text pipeline.

This native no-GPU PHP slice stays at the parser boundary. PDF content-stream `Do` image invocation requires exactly one XObject resource-name operand. An earlier accepted markerPDF slice already stopped extra-operand `Do` calls from painting images; this slice adds review-only malformed operand diagnostics to the referenced Image XObject row without treating it as visible content.

## Behavior

`PdfTextExtractor` now records malformed Image XObject `Do` operands when a `Do` operator has exactly one image resource-name operand plus extra operands:

- the image resource remains `invoked=false` and `invocation_count=0`;
- the review row exposes `malformed_do_operand_count`, `malformed_do_operands`, `malformed_do_operand_policy`, and `malformed_do_operand_review_only`;
- diagnostic rows include operand type/preview lists, the resource operand index, CTM matrices, bbox previews, and a fail-closed `extra_do_operands` reason;
- `Do` tokens inside text objects and compatibility sections remain ignored;
- valid sibling Image XObject invocations still paint and retain CTM/bbox metadata;
- raster/image payload bytes remain excluded from plain text and review JSON.

The existing WordPress smoke now asserts those malformed operand metadata fields in addition to the older unpainted/painted split.

## Red First

After adding `PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php` on the accepted base before the source change:

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php
Focused test run: 1 selected test files (root lock skipped)
PHP Warning: Undefined array key "malformed_do_operand_count" ...
FAIL records malformed Image XObject Do operands as unpainted review-only boundaries
Expected: 1
Actual: NULL
1 test files, 11 assertions, 1 failures
```

The existing image boundary guard rejected the malformed paint operation, but it did not expose the review-only malformed operand diagnostics needed for media import triage.

## Verification

```text
php -l lanes/markerpdf/src/PdfTextExtractor.php
No syntax errors detected in lanes/markerpdf/src/PdfTextExtractor.php
```

```text
php -l lanes/markerpdf/tests/PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php
No syntax errors detected in lanes/markerpdf/tests/PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php
```

```text
php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-do-operand-boundary-currentbase.php
No syntax errors detected in lanes/markerpdf/examples/wordpress-pdf-image-xobject-do-operand-boundary-currentbase.php
```

```text
php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectMalformedSubtypeBoundaryCurrentBaseTest.php
Focused test run: 4 selected test files (root lock skipped)
4 test files, 1409 assertions, 0 failures
```

```text
php lanes/markerpdf/examples/wordpress-pdf-image-xobject-do-operand-boundary-currentbase.php
```

The smoke exits 0 and emits `malformed_do_operand_count=1`, `malformed_do_operand_policy=reject_malformed_image_xobject_do_operands`, `malformed_do_operand_reason=extra_do_operands`, `valid_sibling_image_painted=true`, `payload_in_visible_text=false`, `executes_python_or_models=false`, and `executes_external_pdf_tools=false`.

Root harness: not run - isolated micro-slice.

## Status Delta

- `phpPass`: `2945 -> 2946`
- New focused PHP behavior test: `PdfImageXObjectDoOperandBoundaryCurrentBaseTest.php`
- Focused assertion delta in the new test: `+60`
- WordPress smoke file count unchanged; the existing `wordpress-pdf-image-xobject-do-operand-boundary-currentbase.php` smoke now asserts the new diagnostics.
- Manifest denominator unchanged; this refines the already mapped Image XObject `Do` operand boundary with review metadata.

## Non-Overlap

This does not repeat the accepted single-name operand paint rejection from `20260605T102301Z`; that accepted patch stopped malformed `Do` calls from being counted as invocations. This slice adds per-resource malformed operand review metadata while preserving the accepted unpainted result.

It also does not repeat accepted Image XObject payload exclusion, CTM placement, page/Form resource inheritance, optional content, OCMD, artifact suppression, clipping, page box clipping, rotation/UserUnit display geometry, exact-generation review, SMask/Mask metadata, ColorKey masks, named ColorSpace resources, ExtGState transparency review, JPX `SMaskInData`, DCT/CCITT/JPX/JBIG2 filter review, inline-image tokenizer boundaries, top-level resource dictionary parsing, malformed `cm` operand rejection, malformed subtype rejection, xref repair, or PageLabels/parser stream-filter slices.

## Dependency Closure

No new support component is needed. This reuses the native PHP PDF object scanner, content tokenizer, graphics-state/CTM tracking, resource dictionary resolver, Form XObject traversal, Image XObject review rows, stream decoders, and WordPress smoke renderer.

Full upstream raster parity remains gated on PDFium/pypdfium/PIL or a future native raster backend, and live OCR/model execution remains intentionally out of scope under the current no-GPU markerPDF direction.
