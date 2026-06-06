# markerPDF text-markup annotation indirect operands

Micro-slice: `markerpdf-annotations-links-boundary-current-base-20260606T223147Z`

Base accepted HEAD: `efd3912412f32e446cb9c14dd4c526ece9f557c9`

## Source Truth

Upstream markerPDF uses native PDF page annotation geometry/review data through pdftext/PDFium before Markdown/WordPress rendering. This slice keeps the no-GPU boundary and maps a native parser behavior: text-markup annotation dictionary operands may be stored as generation-qualified indirect PDF objects, and only exact `N G R` references should feed review metadata.

## Behavior

`PdfMarkupAnnotationExtractor` now resolves exact-generation indirect operands for markup annotation `/Subtype`, `/Rect`, `/QuadPoints`, `/Contents`, `/T`, `/Subj`, `/M`, `/NM`, `/C`, `/CA`, `/F`, `/StructParent`, `/Border`, and `/BS` style fields before applying highlight/underline review metadata to supplied WordPress spans. Wrong-generation scalar/array/string operands remain unresolved, so stale annotation dictionaries cannot create review spans or visible payload text.

## Red-First Evidence

Before the implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationIndirectOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 2 assertions, 1 failures`; the stale wrong-generation underline shifted the recovered markup annotation object ids from expected `[7,8]` to `[8,9]`.

## Verification

After the implementation:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationIndirectOperandBoundaryCurrentBaseTest.php`

Result: `1 test files, 33 assertions, 0 failures`.

Adjacent annotation/link family:

`php tools/run-tests.php lanes/markerpdf/tests/PdfMarkupAnnotationIndirectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfMarkupAnnotationExtractorTest.php lanes/markerpdf/tests/PdfPageStructParentMarkupAnnotationContextCurrentBaseTest.php lanes/markerpdf/tests/PdfPageStructParentsThreadMarkupCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectNumericBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationMalformedQuadPointsBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfLinkAnnotationRectOperandBoundaryCurrentBaseTest.php lanes/markerpdf/tests/PdfAnnotationExtractorTest.php`

Result: `8 test files, 572 assertions, 0 failures`.

WordPress smoke:

`php lanes/markerpdf/examples/wordpress-pdf-markup-annotation-indirect-operands-currentbase.php`

Result: emits `markup_annotation_objects=[7,8]`, attaches highlight and underline review metadata, excludes the wrong-generation annotation, keeps annotation payload text out of visible paragraphs, and reports no PDF action, Python/model, OCR, or external PDF tool execution.

## Non-Overlap

This avoids the accepted link-annotation indirect numeric boundary and link geometry slices. Link annotations already resolved indirect numeric operands for `/Rect` and `/QuadPoints`; this patch applies equivalent exact-generation operand discipline to text-markup annotations and their review metadata fields.

## Dependency Closure

No new support component is needed. The slice reuses the lane-local native PDF parser/object table helpers and does not require GPU/model execution, Surya, Texify, Torch, live OCR, Streamlit/FastAPI model workers, pypdfium/PIL rasterization, external PDF tools, or PDF action execution.
