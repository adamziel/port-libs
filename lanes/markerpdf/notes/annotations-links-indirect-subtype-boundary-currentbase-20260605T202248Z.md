# Link Annotation Indirect Subtype Boundary Current Base

Slice: `markerpdf-annotations-links-boundary-current-base-20260605T202248Z`

Accepted base: `9aa35d009f07fabee9a32a57e5e751856e526db5`

## Source Truth

- PDF dictionary values can be indirect objects, including name operands such
  as `/Subtype`, annotation highlight mode `/H`, and border-style `/BS /S`.
- A page annotation with `/Subtype 20 0 R` where object `20 0 obj /Link endobj`
  is a Link annotation for native review and WordPress span promotion.
- A page annotation with `/Subtype 21 0 R` where object `21 0 obj /Text endobj`
  remains non-clickable review metadata even if it carries a URI action.
- Upstream markerPDF keeps PDF annotation/link extraction in the searchable-PDF
  parser path; this slice does not execute PDF actions, OCR, models, Python,
  pypdfium/PIL rendering, or external PDF tools.

## Behavior

- `PdfAnnotationExtractor` resolves indirect annotation subtype name objects
  before generic annotation review, popup suppression, and annotation-thread
  classification.
- `PdfLinkAnnotationExtractor` resolves indirect `/Subtype` names before Link
  promotion, and resolves indirect `/H` plus `/BS /S` presentation names for
  review metadata.
- Indirect non-Link annotation subtypes stay out of promoted WordPress links
  and out of visible text.

## Evidence

Red-first on accepted base after adding the focused test and before source
edits:

`php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectSubtypeBoundaryCurrentBaseTest.php`

Result: `1 test files, 3 assertions, 1 failures`; expected annotation subtypes
`["Link","Text"]`, actual `["Unknown","Unknown"]`.

After source edits:

- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotationIndirectSubtypeBoundaryCurrentBaseTest.php`
  => `1 test files, 27 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfLinkAnnotation*Test.php`
  => `29 test files, 1020 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/PdfAnnotation*Test.php lanes/markerpdf/tests/PdfPageAnnotation*Test.php`
  => `14 test files, 874 assertions, 0 failures`
- `php tools/run-tests.php lanes/markerpdf/tests/Pdf*Action*Test.php`
  => `50 test files, 2764 assertions, 0 failures`
- `php lanes/markerpdf/examples/wordpress-pdf-link-annotation-indirect-subtype-currentbase.php`
  => emitted `annotation_subtypes=["Link","Text"]`,
  `promoted_link_objects=[7]`,
  `promoted_uri=https://example.com/indirect-annotation-subtype`,
  `border_style=dashed`, `nonlink_indirect_subtype_excluded=true`,
  `annotation_payload_text_visible=false`, and all PDF action, Python/model,
  and external-tool execution flags false.

Root harness: not run - isolated micro-slice.

## Non-Overlap

This does not repeat accepted direct Link annotations, escaped Link dictionary
keys, annotation object/page generation boundaries, indirect action subtype
`/S`, object-stream action dictionaries, primary action array/scalar rejection,
QuadPoints, crop/rotation/UserUnit geometry, named-destination limits, widget
link inheritance, or hidden flag behavior. It owns indirect annotation
`/Subtype` name values and directly coupled link presentation name metadata.

## Dependency Closure

No new support component is needed. The patch reuses the native PHP PDF
tokenizer, selected-object resolver, annotation extractor, action review, link
span promotion, and WordPress smoke harness. GPU/model/OCR parity remains
intentionally out of scope under the current markerPDF no-GPU directive.
