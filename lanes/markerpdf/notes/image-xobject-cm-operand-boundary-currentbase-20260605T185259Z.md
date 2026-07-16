# Image XObject cm Operand Boundary Current Base

Slice: `markerpdf-image-xobject-boundary-current-base-20260605T185259Z`

Accepted base: `0743792c5d680e9fed5e8a0846fa60f1ef7412bd`

## Source Truth

- Upstream markerPDF keeps searchable text extraction separate from image
  rendering handoff: text pages are handled by `marker.pdf.extract_text`, while
  image raster work is delegated to `marker.pdf.images.render_image`.
- In the PDF graphics content stream, the `cm` operator takes exactly six
  numeric operands. An extra leading operand before `cm` must not be silently
  folded into an Image XObject placement CTM by taking the last six values.

## Behavior

- `PdfTextExtractor::contentMatrixOperand()` now accepts matrix operators only
  when exactly six numeric operands are present.
- The Image XObject review path still counts the following `/Name Do`
  invocation, but a malformed extra-operand `cm` does not shift the image
  placement bbox. The invocation is recorded under the current CTM instead.
- A valid sibling image invocation in the same page continues to preserve its
  CTM, bbox, decoded filter metadata, and payload exclusion from visible
  WordPress text.

## Evidence

Red-first on accepted base after adding the focused test and before the source
edit:

`php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 11 assertions, 1 failures`; expected identity matrix for
the malformed `cm`, actual matrix was `[20, 0, 0, 10, 72, 690]`.

After the source edit:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php`
  => `1 test files, 26 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfImageXObjectBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfPageResourceImageXObjectInheritanceCurrentBaseTest.php`
  => `3 test files, 1033 assertions, 0 failures`
- `php -l lanes/markerpdf/src/PdfTextExtractor.php`
  => no syntax errors
- `php -l lanes/markerpdf/tests/PdfImageXObjectCmOperandBoundaryCurrentBaseTest.php`
  => no syntax errors
- `php -l lanes/markerpdf/examples/wordpress-pdf-image-xobject-cm-operand-boundary-currentbase.php`
  => no syntax errors
- `php lanes/markerpdf/examples/wordpress-pdf-image-xobject-cm-operand-boundary-currentbase.php`
  => emitted `malformed_cm_transform_rejected=true`,
  `valid_sibling_image_painted=true`, `payload_in_visible_text=false`
- `git diff --check -- lanes/markerpdf`
  => clean

Root harness: not run - isolated micro-slice.

## Non-overlap

This slice does not repeat the accepted Image XObject Do operand boundary,
text-object Do boundary, compatibility-section boundary, optional-content,
clipping, Form XObject Matrix, color-space, image-mask, tiling-pattern, or
encrypted fail-closed image review slices. It owns the graphics-state `cm`
operand arity boundary immediately before Image XObject placement.

## Dependency Closure

No new support component is needed. The patch reuses the existing native PHP
content stream tokenizer, graphics-state matrix helpers, stream filters, and
WordPress example harness. No GPU/model execution, OCR, external PDF tools, or
network services were used.
