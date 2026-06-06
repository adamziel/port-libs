# Link annotation Rect operand boundary current-base

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260606T095239Z`

Accepted base: `0b6666acb8a9aa6e856d8e275d77b28730056167`

## Source truth

- PDF annotation `/Rect` is a four-coordinate rectangle array. For link promotion, malformed operands must not be skipped and recombined into a synthetic clickable rectangle.
- Existing markerPDF link annotation boundaries already cover exact-generation indirect numeric operands and malformed `/QuadPoints` group handling. This slice keeps those accepted behaviors intact while tightening only `/Rect` coordinate admission.
- No upstream `sddai/markerPDF` checkout was present in the local `.upstream-cache`; this handoff uses the native PDF object/dictionary parser behavior in `lanes/markerpdf` plus the accepted lane manifest annotations/link boundary inventory as source truth.

## Behavior

- `PdfAnnotationExtractor` now reports link annotation review rows with `rect=null` when `/Rect` contains a direct `null`, name token, or nonnumeric indirect reference before four numeric coordinates.
- `PdfLinkAnnotationExtractor` now rejects those malformed `/Rect` arrays before WordPress span promotion.
- Exact-generation indirect numeric operands still resolve for valid `/Rect` arrays; wrong-generation or nonnumeric references fail closed.
- Generic tolerant number-array parsing remains unchanged for page boxes, colors, and other metadata arrays.

## Red-first evidence

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php`

Result before source edit:

`1 test files, 4 assertions, 1 failures`

Failure boundary:

`[160 700 null 250 718]` was collapsed into `[160.0, 700.0, 250.0, 718.0]`.

## Focused verification

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php`

Result:

`1 test files, 30 assertions, 0 failures`

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationCropBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationExtractorTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php`

Result:

`6 test files, 507 assertions, 0 failures`

`php lanes/markerpdf/examples/wordpress-pdf-link-rect-operand-boundary-currentbase.php`

Result:

- `promoted_link_objects=[7]`
- `malformed_null_rect_linked=false`
- `malformed_name_rect_linked=false`
- `malformed_reference_rect_linked=false`
- `annotation_payload_text_visible=false`
- `executes_python_or_models=false`
- `executes_external_pdf_tools=false`

Root harness: not run - isolated micro-slice.

## Non-overlap

This does not repeat accepted annotation/link work for page generation boundaries, destination generation boundaries, indirect `/Annots` array fragments, URI catalog base/control boundaries, `IsMap`, name-tree limits, exact-generation indirect numeric coordinates, or malformed `/QuadPoints` group skipping. The adjacent focused run includes the indirect numeric and malformed QuadPoints files to prove those accepted surfaces still pass.

## Dependency closure

No new support component is needed. The slice reuses the existing native PDF object parser, indirect-reference resolver, and WordPress span-link postprocessor. GPU/model OCR, PDFium/PIL, Python marker workers, PDF actions, JavaScript, and external PDF tools remain intentionally out of scope.
